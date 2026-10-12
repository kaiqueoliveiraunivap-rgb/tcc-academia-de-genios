"""Extrai PDF local por intervalos explícitos; não libera conteúdo sem revisão.
Uso: python extrair_livro.py livro.pdf manifesto.json saida.json
Dependência: pypdf. Páginas são físicas (1-based), não números impressos.
"""
import hashlib
import json
import sys
from pathlib import Path
from pypdf import PdfReader

def extrair(pdf, manifesto):
    leitor = PdfReader(pdf)
    dados = json.loads(Path(manifesto).read_text(encoding='utf-8-sig'))
    dados['sha256_pdf'] = hashlib.sha256(Path(pdf).read_bytes()).hexdigest()
    ocupadas = set()
    for cap in dados['capitulos']:
        inicio, fim = cap['inicio'], cap['fim']
        if not 1 <= inicio <= fim <= len(leitor.pages):
            raise ValueError('Intervalo fora do PDF')
        if ocupadas.intersection(range(inicio, fim + 1)):
            raise ValueError('Capítulos sobrepostos')
        ocupadas.update(range(inicio, fim + 1))
        cap['paginas'] = [{'pagina': n, 'texto': leitor.pages[n-1].extract_text() or ''} for n in range(inicio, fim+1)]
        cap['revisado'] = False
    return dados

if __name__ == '__main__':
    if len(sys.argv) != 4:
        raise SystemExit(__doc__)
    resultado = extrair(sys.argv[1], sys.argv[2])
    Path(sys.argv[3]).write_text(json.dumps(resultado, ensure_ascii=False, indent=2), encoding='utf-8')
    print('Extraído como rascunho. Revise texto, fórmulas e limites antes de importar.')
