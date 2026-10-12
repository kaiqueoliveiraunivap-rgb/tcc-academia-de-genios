"""Prepara um PDF didático para a importação local, usando seus marcadores.

Uso: python preparar_pdf.py livro.pdf saida.json --titulo ... --materia ... --fonte-url ...
Use --inspect para conferir frentes/capítulos sem extrair o texto.
Dependência: pypdf e pdftotext (Poppler).
"""

import argparse
import hashlib
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

from pypdf import PdfReader


FRONT = re.compile(r"^frente\s*(?:[0-9]+|[ivx]+|[úu]nica)\s*$", re.IGNORECASE)
CHAPTER = re.compile(r"\b(?:cap[ií]tulo|cap[.\s]*[0-9]+|chapter)\b", re.IGNORECASE)
CHAPTER_NUMBER = re.compile(r"\b(?:cap[ií]tulo|cap)\s*\.?\s*([0-9]+)\b", re.IGNORECASE)
END = re.compile(r"^(?:gabarito|resolu[cç][oõ]es|respostas|resumindo\s+frente\s*[0-9]+|refer[eê]ncias\s+bibliogr[aá]ficas)\b", re.IGNORECASE)
MAX_JSON_BYTES = 150_000


def outline_entries(reader):
    """Devolve os marcadores em ordem, com frente herdada da árvore do PDF."""
    entries = []

    def visit(items, inherited_front=None):
        current_front = inherited_front
        for item in items:
            if isinstance(item, list):
                visit(item, current_front)
                continue
            title = str(getattr(item, "title", "")).strip()
            try:
                page = reader.get_destination_page_number(item) + 1
            except (ValueError, KeyError, AttributeError):
                continue
            if not 1 <= page <= len(reader.pages):
                continue
            if FRONT.search(title):
                current_front = title
                kind = "front"
            elif CHAPTER.search(title):
                kind = "chapter"
            elif END.search(title):
                kind = "end"
            else:
                kind = "other"
            entries.append({"kind": kind, "title": title, "page": page, "front": current_front})

    visit(reader.outline)
    return entries


def clean_title(title, fallback):
    title = re.sub(r"\s+", " ", title).strip(" -–—.\t")
    if not title or "\ufffd" in title or len(title) > 180:
        return fallback
    return title


def page_texts(pdf_path, pdf_to_text, expected_pages):
    command = [pdf_to_text, "-enc", "UTF-8", str(pdf_path), "-"]
    result = subprocess.run(command, capture_output=True, check=True)
    text = result.stdout.decode("utf-8", errors="replace")
    pages = text.split("\f")
    if pages and not pages[-1].strip():
        pages.pop()
    if len(pages) == expected_pages:
        return [part.strip() for part in pages]
    # Alguns PDFs têm caracteres de form feed no conteúdo de uma página.
    # Extraí-la separadamente mantém a numeração física usada nas citações.
    separate = []
    for page in range(1, expected_pages + 1):
        command = [pdf_to_text, "-f", str(page), "-l", str(page), "-enc", "UTF-8", str(pdf_path), "-"]
        result = subprocess.run(command, capture_output=True, check=True)
        separate.append(result.stdout.decode("utf-8", errors="replace").replace("\f", "\n").strip())
    return separate


def make_pages(texts, start, stop):
    return [{"pagina": page, "texto": texts[page - 1]} for page in range(start, stop) if page <= len(texts)]


def split_long_chapter(title, pages):
    chunks = []
    current = []
    for page in pages:
        candidate = current + [page]
        size = len(json.dumps(candidate, ensure_ascii=False).encode("utf-8"))
        if size > MAX_JSON_BYTES and current:
            chunks.append(current)
            current = [page]
        else:
            current = candidate
        if len(json.dumps(current, ensure_ascii=False).encode("utf-8")) > MAX_JSON_BYTES:
            raise ValueError(f"Página {page['pagina']} excede o limite de texto de um capítulo")
    if current:
        chunks.append(current)
    return [
        {"titulo": title if len(chunks) == 1 else f"{title} — parte {i + 1}",
         "revisado": True, "paginas": chunk}
        for i, chunk in enumerate(chunks)
    ]


def introduction_page(texts, start, number):
    """Marcadores apontam às vezes para a página depois da abertura."""
    if start <= 1:
        return start
    previous = texts[start - 2]
    if re.search(r"(?m)^\s*CAP[ÍI]TULO\b", previous):
        match = re.search(r"(?m)^\s*([0-9]{1,2})\s+", previous)
        if match and match.group(1) == number:
            return start - 1
    return start


def title_from_page(text, number):
    lines = text.splitlines()
    for index, line in enumerate(lines[:20]):
        if not re.search(r"\bCAP[ÍI]TULO\b", line):
            continue
        same_line = re.search(r"\bCAP[ÍI]TULO\s{2,}(.+)", line)
        candidates = [same_line.group(1)] if same_line else lines[index + 1:index + 9]
        for candidate in candidates:
            title = re.split(r"\s{5,}", candidate.strip())[0].strip()
            if (4 <= len(title) <= 140 and len(title.split()) <= 12
                    and not re.search(r"shutterstock|biblioteca|^frente\b", title, re.IGNORECASE)
                    and not title.endswith((".", ";", ":"))):
                return f"Capítulo {number} — {title}"
    return None


def prepare(reader, entries, texts, args):
    chapter_entries = [e for e in entries if e["kind"] == "chapter"]
    if not chapter_entries:
        raise ValueError("O PDF não possui marcadores de capítulos; revisão necessária")
    if len(texts) != len(reader.pages):
        raise ValueError(f"Extração incompleta: {len(texts)} textos para {len(reader.pages)} páginas")
    fronts = []
    current = None
    last_start = 0
    for index, entry in enumerate(chapter_entries):
        number_match = CHAPTER_NUMBER.search(entry["title"])
        ordinal = number_match.group(1) if number_match else str(index + 1)
        start = introduction_page(texts, entry["page"], ordinal)
        if start <= last_start:
            raise ValueError("Marcadores de capítulos repetidos ou fora de ordem")
        last_start = start
        next_chapter = len(texts) + 1
        if index + 1 < len(chapter_entries):
            next_entry = chapter_entries[index + 1]
            next_number = CHAPTER_NUMBER.search(next_entry["title"])
            next_chapter = introduction_page(texts, next_entry["page"], next_number.group(1) if next_number else str(index + 2))
        next_boundary = min(
            [e["page"] for e in entries if e["kind"] in ("front", "end") and start < e["page"] <= next_chapter]
            or [next_chapter]
        )
        stop = min(next_chapter, next_boundary)
        for page in range(start + 2, stop):
            heading = "\n".join(texts[page - 1].splitlines()[:10])
            if re.search(r"(?im)^\s*GABARITO\s*$", heading):
                stop = page
                break
        front_title = clean_title(entry["front"] or "", "Frente única")
        if current is None or current["titulo"] != front_title:
            current = {"titulo": front_title, "capitulos": []}
            fronts.append(current)
        title = title_from_page(texts[start - 1], ordinal) or clean_title(entry["title"], f"Capítulo {ordinal}")
        pages = make_pages(texts, start, stop)
        text = "".join(page["texto"] for page in pages)
        if len(text) < 500 or text.count("\ufffd") > max(5, len(text) // 100):
            raise ValueError(f"Texto insuficiente ou ilegível no capítulo iniciado na página {start}")
        current["capitulos"].extend(split_long_chapter(title, pages))
    with open(args.pdf, "rb") as source:
        pdf_hash = hashlib.file_digest(source, "sha256").hexdigest()
    return {
        "titulo": args.titulo,
        "materia": args.materia,
        "fonte_url": args.fonte_url,
        "sha256_pdf": pdf_hash,
        "frentes": fronts,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pdf", type=Path)
    parser.add_argument("output", nargs="?", type=Path)
    parser.add_argument("--titulo")
    parser.add_argument("--materia")
    parser.add_argument("--fonte-url")
    parser.add_argument("--inspect", action="store_true")
    parser.add_argument("--mapa", type=Path, help="Mapa revisado de páginas físicas, inclusive início/fim")
    args = parser.parse_args()
    reader = PdfReader(args.pdf)
    entries = outline_entries(reader)
    if args.inspect:
        summary = {
            "pages": len(reader.pages),
            "fronts": [{"title": e["title"], "page": e["page"]} for e in entries if e["kind"] == "front"],
            "chapters": len([e for e in entries if e["kind"] == "chapter"]),
            "first_chapters": [{"title": e["title"], "page": e["page"]} for e in entries if e["kind"] == "chapter"][:2],
            "ends": [{"title": e["title"], "page": e["page"]} for e in entries if e["kind"] == "end"],
        }
        print(json.dumps(summary, ensure_ascii=False))
        return
    if not args.output or not args.titulo or not args.materia or not args.fonte_url:
        parser.error("informe saída, --titulo, --materia e --fonte-url")
    pdf_to_text = shutil.which("pdftotext") or "C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe"
    texts = page_texts(args.pdf, pdf_to_text, len(reader.pages))
    if args.mapa:
        mapping = json.loads(args.mapa.read_text(encoding="utf-8"))
        fronts = []
        last = 0
        for chapter in mapping:
            start, end = chapter["inicio"], chapter["fim"]
            if start <= last or end < start or end > len(texts):
                raise ValueError("Mapa contém páginas inexistentes ou sobrepostas")
            last = end
            pages = make_pages(texts, start, end + 1)
            text = "".join(p["texto"] for p in pages)
            if len(text) < 500 or text.count("\ufffd") > max(5, len(text) // 100):
                raise ValueError("Texto insuficiente ou ilegível; verifique o PDF/OCR")
            if not fronts or fronts[-1]["titulo"] != chapter["frente"]:
                fronts.append({"titulo": chapter["frente"], "capitulos": []})
            fronts[-1]["capitulos"].extend(split_long_chapter(chapter["titulo"], pages))
        with args.pdf.open("rb") as source:
            digest = hashlib.file_digest(source, "sha256").hexdigest()
        result = {"titulo": args.titulo, "materia": args.materia, "fonte_url": args.fonte_url,
                  "sha256_pdf": digest, "frentes": fronts}
    else:
        result = prepare(reader, entries, texts, args)
    args.output.write_text(json.dumps(result, ensure_ascii=False), encoding="utf-8")
    print(json.dumps({"frentes": len(result["frentes"]), "capitulos": sum(len(f["capitulos"]) for f in result["frentes"])}, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"PDF pendente de revisão: {exc}", file=sys.stderr)
        sys.exit(1)
