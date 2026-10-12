<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../estudo_formato.php';
$texto='Uma razão compara duas grandezas por uma divisão. A ordem das grandezas determina o significado da comparação. Antes de calcular, identifique os valores, verifique as unidades e interprete o resultado de acordo com as grandezas comparadas.';
$paginas=[['pagina'=>3,'texto'=>str_repeat($texto.' ',45)],['pagina'=>4,'texto'=>$texto]];
$fontes=prepararFontesEstudo($paginas);
foreach ($paginas as $p) {
    $partes=array_filter($fontes,fn($f)=>$f['pagina']===$p['pagina']);
    if (implode(' ',array_column($partes,'trecho'))!==normalizarFonte($p['texto'])) throw new RuntimeException('Texto perdido na divisão do capítulo.');
}
$dados=['resumo'=>[],'questoes'=>[],'palavras_chave'=>['Razão','Grandezas','Divisão','Unidades','Comparação']];
for ($i=0;$i<6;$i++) $dados['resumo'][]=['titulo'=>'Conceito '.$i,'texto'=>str_repeat($texto.' ',4),'topicos'=>$i<2 ? ['Compare a ordem das grandezas.','Verifique as unidades antes de dividir.'] : [],'referencia'=>'R1'];
for ($i=0;$i<5;$i++) $dados['questoes'][]=['enunciado'=>'Questão '.$i,'explicacao'=>$texto,'referencia'=>'R1','alternativas'=>[
    ['texto'=>'A','ehCorreta'=>true],['texto'=>'B','ehCorreta'=>false],['texto'=>'C','ehCorreta'=>false],['texto'=>'D','ehCorreta'=>false]
]];
$valido=validarEstudoCompleto(resolverReferenciasEstudo($dados,$fontes),$paginas);
$casos=[];
$x=$valido; $x['palavras_chave']=[]; $casos[]=$x;
$x=$valido; $x['palavras_chave'][1]=$x['palavras_chave'][0]; $casos[]=$x;
$x=$valido; foreach($x['resumo'] as &$p) $p['topicos']=[]; unset($p); $casos[]=$x;
$curto=$valido; foreach($curto['resumo'] as &$p) $p['texto']='Explicação muito curta.'; unset($p); $casos[]=$curto;
foreach (['Na página 3 há uma definição.','Segundo o livro, qual é a razão?','De acordo com o texto, escolha a opção.','No capítulo são apresentadas razões.'] as $textoInvalido) {
    $x=$valido; $x['questoes'][0]['enunciado']=$textoInvalido; $casos[]=$x;
}
$x=$valido; $x['questoes'][0]['explicacao']='Veja na página 3.'; $casos[]=$x;
$x=$valido; $x['questoes'][0]['alternativas'][0]['texto']='Conforme o texto, alternativa A'; $casos[]=$x;
foreach ($casos as $x) {
    try { validarEstudoCompleto($x,$paginas); } catch (RuntimeException $e) { continue; }
    throw new RuntimeException('Conteúdo superficial ou referência externa foram aceitos.');
}
$x=$dados; $x['questoes'][0]['referencia']='Rinexistente';
try { resolverReferenciasEstudo($x,$fontes); throw new LogicException('Referência inventada aceita.'); } catch (RuntimeException $e) {}
echo "OK: capítulo completo preservado, referências recuperadas do original e textos inadequados rejeitados.\n";
