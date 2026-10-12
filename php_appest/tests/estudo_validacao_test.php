<?php
require_once __DIR__.'/../estudo_validacao.php';
$paginas=[['pagina'=>2,'texto'=>'Esta é uma evidência literal do livro para os testes de referência.']];
$ref=['pagina'=>2,'trecho'=>'Esta é uma evidência literal do livro'];
$dados=['resumo'=>[array_merge(['texto'=>'Resumo de teste'],$ref)],'questoes'=>[]];
for($i=0;$i<5;$i++) $dados['questoes'][]=array_merge(['enunciado'=>'Pergunta '.$i,'explicacao'=>'Explicação','alternativas'=>[['texto'=>'A','ehCorreta'=>true],['texto'=>'B','ehCorreta'=>false],['texto'=>'C','ehCorreta'=>false],['texto'=>'D','ehCorreta'=>false]]],$ref);
validarEstudo($dados,$paginas);
$tabela=[['pagina'=>93,'texto'=>'Unidade de volume 1 m3 1 dm3 1 cm3\nUnidade de capacidade 1 000 litros 1 litro 1 mililitro']];
$ajustado=ajustarTrechosLiterais(['resumo'=>[['texto'=>'Um decímetro cúbico corresponde a um litro.','pagina'=>93,'trecho'=>'1 dm3 1 litro']],'questoes'=>[]],$tabela);
if (strlen($ajustado['resumo'][0]['trecho'])<20 || !str_contains(preg_replace('/\s+/u',' ',$tabela[0]['texto']),$ajustado['resumo'][0]['trecho'])) throw new RuntimeException('Reparo da referência de tabela falhou.');
$quebra=[['pagina'=>94,'texto'=>"Porém, para economizar tempo ao realizar essas conver-\nsões, utilizamos a seguinte regra prática."]];
$comQuebra=['resumo'=>[['texto'=>'Regra prática','pagina'=>94,'trecho'=>'Porém, para economizar tempo ao realizar essas conversões, utilizamos a seguinte regra prática.']],'questoes'=>[]];
$comQuebra['questoes']=$dados['questoes'];
foreach ($comQuebra['questoes'] as &$q) { $q['pagina']=94; $q['trecho']=$comQuebra['resumo'][0]['trecho']; } unset($q);
validarEstudo($comQuebra,$quebra);
$colunas=[['pagina'=>21,'texto'=>'Essa é a nossa primeira propriedade, que pode ser ge- uma base específica, neralizada como: Propriedade 1']];
$citacaoColunas='Essa é a nossa primeira propriedade, que pode ser generalizada como: Propriedade 1';
$recuperado=ajustarTrechosLiterais(['resumo'=>[['texto'=>'Potências','pagina'=>21,'trecho'=>$citacaoColunas]]],$colunas);
if (!str_contains(normalizarFonte($colunas[0]['texto']),$recuperado['resumo'][0]['trecho']) || mb_strlen($recuperado['resumo'][0]['trecho'])<24) throw new RuntimeException('Trecho de colunas de Matemática não foi recuperado.');
$citacaoInventada='Uma regra matemática completamente ausente desta página';
$inventada=ajustarTrechosLiterais(['resumo'=>[['texto'=>'Invenção','pagina'=>21,'trecho'=>$citacaoInventada]]],$colunas);
if ($inventada['resumo'][0]['trecho']!==$citacaoInventada) throw new RuntimeException('Trecho inventado foi recuperado indevidamente.');
$inventado=$dados;
$inventado['resumo'][0]['trecho']='Trecho fictício sem correspondência';
try { validarEstudo(ajustarTrechosLiterais($inventado,$paginas),$paginas); throw new LogicException('Referência inventada foi aceita.'); }
catch (RuntimeException $esperado) {}
$casos=[];
$x=$dados; $x['questoes'][0]['pagina']=99; $casos[]=$x;
$x=$dados; $x['resumo'][0]['trecho']='Trecho inventado inexistente no livro'; $casos[]=$x;
$x=$dados; $x['questoes'][0]['alternativas'][1]['ehCorreta']=true; $casos[]=$x;
$x=$dados; array_pop($x['questoes']); $casos[]=$x;
$x=$dados; $x['questoes'][0]['alternativas'][0]['ehCorreta']='true'; $casos[]=$x;
$x=$dados; $x['questoes'][1]=$x['questoes'][0]; $casos[]=$x;
$x=$dados; $x['questoes'][0]['alternativas'][1]['texto']='A'; $casos[]=$x;
foreach($casos as $i=>$caso) {
    try { validarEstudo($caso,$paginas); } catch (Throwable $e) { continue; }
    fwrite(STDERR,"Falha: caso $i aceito\n"); exit(1);
}
echo "OK: estudo válido e 7 rejeições de conteúdo inválido.\n";
