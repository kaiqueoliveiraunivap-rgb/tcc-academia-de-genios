<?php
// Sem dependências de HTTP/banco: usado também nos testes.
function normalizarFonte($texto) {
    // PDFs quebram palavras entre linhas, por exemplo "conver-\nsões".
    $texto=preg_replace('/(?<=\pL)-[ \t]*\r?\n\s*(?=\pL)/u','',$texto);
    return preg_replace('/\s+/u',' ',trim($texto));
}

/**
 * O OCR pode intercalar duas colunas ou trocar símbolos no fim da frase.
 * Aproveita somente uma sequência contínua de palavras que exista na própria
 * página citada. Uma semelhança vaga não basta para substituir a evidência.
 */
function trechoLiteralDaPagina($fonte, $citacao) {
    $palavras=preg_split('/\s+/u',trim($citacao));
    if (count($palavras)<3) return null;
    $melhor=null;
    $tamanho=0;
    for ($quantidade=count($palavras); $quantidade>=3; $quantidade--) {
        for ($inicio=0; $inicio+$quantidade<=count($palavras); $inicio++) {
            $candidato=implode(' ',array_slice($palavras,$inicio,$quantidade));
            $comprimento=mb_strlen($candidato);
            if ($comprimento<$tamanho || $comprimento<24) continue;
            $posicao=mb_stripos($fonte,$candidato);
            if ($posicao===false) continue;
            $melhor=mb_substr($fonte,$posicao,$comprimento);
            $tamanho=$comprimento;
        }
        if ($melhor!==null) break;
    }
    $minimo=max(24,(int)ceil(mb_strlen($citacao)*0.35));
    return $tamanho>=$minimo ? $melhor : null;
}

function ajustarTrechosLiterais($dados, $paginas) {
    if (!is_array($dados)) return $dados;
    $fontes=[];
    foreach ($paginas as $p) $fontes[$p['pagina']]=normalizarFonte($p['texto']);
    foreach (['resumo','questoes'] as $secao) {
        if (!is_array($dados[$secao] ?? null)) continue;
        foreach ($dados[$secao] as &$item) {
            $pagina=$item['pagina'] ?? null;
            $trecho=$item['trecho'] ?? null;
            if (!is_int($pagina) || !isset($fontes[$pagina]) || !is_string($trecho)) continue;
            $fonte=$fontes[$pagina];
            $citacao=normalizarFonte($trecho);
            if (mb_strlen($citacao)<8) continue;
            if (strlen($citacao)>=20 && strpos($fonte,$citacao)!==false) continue;
            $inicio=strpos($fonte,$citacao);
            $fim=$inicio===false ? 0 : $inicio+strlen($citacao);
            if ($inicio===false) {
                // Algumas tabelas são lidas pela IA como uma frase. Aceita-se apenas
                // uma sequência de palavras presente, em ordem, na mesma página.
                $palavras=preg_split('/\s+/u',$citacao);
                if (count($palavras)<3 || count(array_filter($palavras,fn($p)=>mb_strlen($p)>=3))<2) continue;
                $padrao=fn($p)=>'~(?<![\pL\pN])'.preg_quote($p,'~').'(?![\pL\pN])~u';
                $ocorrencias=[];
                preg_match_all($padrao($palavras[0]),$fonte,$ocorrencias,PREG_OFFSET_CAPTURE);
                $melhor=null;
                foreach ($ocorrencias[0] ?? [] as [$texto,$posicao]) {
                    $cursor=$posicao+strlen($texto);
                    foreach (array_slice($palavras,1) as $palavra) {
                        if (!preg_match($padrao($palavra),$fonte,$achado,PREG_OFFSET_CAPTURE,$cursor)) { $cursor=-1; break; }
                        $cursor=$achado[0][1]+strlen($achado[0][0]);
                        if ($cursor-$posicao>180) { $cursor=-1; break; }
                    }
                    if ($cursor>0 && ($melhor===null || $cursor-$posicao<$melhor[1]-$melhor[0])) $melhor=[$posicao,$cursor];
                }
                if ($melhor===null) {
                    $literal=trechoLiteralDaPagina($fonte,$citacao);
                    if ($literal!==null) $item['trecho']=$literal;
                    continue;
                }
                [$inicio,$fim]=$melhor;
            }
            $inicio=max(0,$inicio-18);
            $fim=min(strlen($fonte),$fim+45);
            $item['trecho']=trim(mb_strcut($fonte,$inicio,$fim-$inicio,'UTF-8'));
        }
        unset($item);
    }
    return $dados;
}

function validarEstudo($dados, $paginas) {
    if (!is_array($dados) || !is_array($dados['resumo'] ?? null) || !is_array($dados['questoes'] ?? null) || count($dados['resumo']) < 1 || count($dados['questoes']) !== 5) {
        throw new RuntimeException('Resumo ou quantidade de questões inválidos.');
    }
    $fontes = [];
    foreach ($paginas as $p) $fontes[$p['pagina']] = $p['texto'];
    $normalizar = fn($s) => normalizarFonte($s);
    foreach (array_merge($dados['resumo'], $dados['questoes']) as $item) {
        if (!is_array($item)) throw new RuntimeException('Parágrafo ou questão inválidos.');
        $texto = $item['texto'] ?? $item['enunciado'] ?? null;
        $pagina = $item['pagina'] ?? null;
        $trecho = $item['trecho'] ?? null;
        if (!is_string($texto) || trim($texto)==='' || !is_int($pagina) || !isset($fontes[$pagina]) || !is_string($trecho) || strlen(trim($trecho))<20 || strpos($normalizar($fontes[$pagina]), $normalizar($trecho)) === false) {
            throw new RuntimeException('Referência não encontrada no capítulo (página '.(string)$pagina.', trecho '.mb_substr((string)$trecho,0,70).').');
        }
    }
    $enunciados = [];
    foreach ($dados['questoes'] as $q) {
        if (!is_array($q)) throw new RuntimeException('Questão inválida.');
        if (!is_array($q['alternativas'] ?? null) || count($q['alternativas'])!==4 || !is_string($q['explicacao'] ?? null) || trim($q['explicacao'])==='') throw new RuntimeException('Questão incompleta.');
        $corretas=0; $textos=[];
        foreach ($q['alternativas'] as $a) {
            if (!is_string($a['texto'] ?? null) || trim($a['texto'])==='' || !is_bool($a['ehCorreta'] ?? null)) throw new RuntimeException('Alternativa inválida.');
            $textos[]=$normalizar($a['texto']);
            if ($a['ehCorreta']) $corretas++;
        }
        if ($corretas!==1 || count(array_unique($textos))!==4) throw new RuntimeException('Gabarito inválido.');
        $enunciados[]=$normalizar($q['enunciado']);
    }
    if (count(array_unique($enunciados))!==5) throw new RuntimeException('Questões repetidas.');
    return $dados;
}
