<?php
// Executar apenas no banco isolado, com GEMINI_API_KEY e GEMINI_MODEL no ambiente.
if (PHP_SAPI!=='cli') exit(1);
require __DIR__.'/../config.php';
if (configuracao('DB_NAME')!=='academia_refatoracao_test' || !configuracao('GEMINI_API_KEY') || !configuracao('GEMINI_MODEL')) {
    fwrite(STDERR,"Configure o banco isolado e o Gemini via ambiente.\n"); exit(1);
}
require __DIR__.'/../conexao.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
function inserirGemini(mysqli $db,string $sql,array $args=[]): int {
    $s=$db->prepare($sql);
    if ($args) $s->bind_param(str_repeat('s',count($args)),...$args);
    $s->execute(); $id=(int)$s->insert_id; $s->close(); return $id;
}
function endpointGemini(array $post): array {
    $p=proc_open([PHP_BINARY,__DIR__.'/endpoint_runner.php','gerar_estudo.php'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),getenv());
    if (!is_resource($p)) throw new RuntimeException('Não foi possível iniciar a API.');
    fwrite($pipes[0],json_encode($post,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $saida=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $erro=stream_get_contents($pipes[2]); fclose($pipes[2]);
    $codigo=proc_close($p);
    if ($codigo!==0) throw new RuntimeException('A API encerrou inesperadamente.');
    preg_match('/HTTP_STATUS=(\d+)/',$erro,$m);
    return [(int)($m[1] ?? 0),json_decode($saida,true,512,JSON_THROW_ON_ERROR)];
}
$ids=[];
try {
    $tag=bin2hex(random_bytes(6));
    $senha=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
    $ids['prof']=inserirGemini($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',
        ['Professor de teste','prof-'.$tag.'@example.invalid',$senha,'professor']);
    $ids['aluno']=inserirGemini($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',
        ['Aluno de teste','aluno-'.$tag.'@example.invalid',$senha,'aluno']);
    $ids['turma']=inserirGemini($conn,'INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',
        ['Turma Gemini '.$tag,'2º Ano','2026',$ids['prof']]);
    inserirGemini($conn,'INSERT INTO matricula(id_turma,id_aluno) VALUES (?,?)',[$ids['turma'],$ids['aluno']]);
    $ids['livro']=inserirGemini($conn,'INSERT INTO livro_didatico(titulo,materia,fonte_url,versao,ativo) VALUES (?,?,?,?,1)',
        ['Apostila de teste','Matemática','https://example.invalid/livro.pdf',hash('sha256',$tag)]);
    $paginas=[
        ['pagina'=>1,'texto'=>'Uma função quadrática tem a forma f(x)=ax²+bx+c, em que a, b e c são números reais e a é diferente de zero. O gráfico dessa função é uma parábola. O coeficiente a determina a concavidade: quando a é positivo, a parábola se abre para cima; quando a é negativo, abre-se para baixo. O termo c indica o ponto em que o gráfico corta o eixo vertical, pois f(0)=c. O domínio é o conjunto dos números reais. Para calcular o valor da função em um número, substituímos x na expressão e efetuamos as operações. Por exemplo, em f(x)=x²-4x+3, temos f(0)=3 e f(2)=4-8+3=-1.'],
        ['pagina'=>2,'texto'=>'As raízes de uma função quadrática são os valores de x para os quais f(x)=0. Elas indicam os pontos em que a parábola encontra o eixo horizontal. Para a equação ax²+bx+c=0, calculamos o discriminante Δ=b²-4ac. Se Δ é positivo, existem duas raízes reais diferentes. Se Δ é zero, existe uma raiz real dupla. Se Δ é negativo, não existem raízes reais. Quando Δ é não negativo, usamos x=(-b±√Δ)/(2a). Na função f(x)=x²-4x+3, temos a=1, b=-4 e c=3. O discriminante é 16-12=4. Assim, as raízes são (4+2)/2=3 e (4-2)/2=1. Podemos conferir substituindo x=1 e x=3 na função; os dois resultados são zero.'],
        ['pagina'=>3,'texto'=>'O vértice da parábola indica um valor máximo ou mínimo da função. Sua abscissa é xv=-b/(2a), e sua ordenada é yv=f(xv). Se a é positivo, o vértice é um ponto de mínimo; se a é negativo, é um ponto de máximo. Na função f(x)=x²-4x+3, temos xv=2 e yv=-1. Portanto, o vértice é (2,-1), e o menor valor da função é -1. A reta x=2 é o eixo de simetria do gráfico. Para desenhar a parábola, podemos marcar as raízes 1 e 3, o ponto (0,3) e o vértice (2,-1). Depois traçamos uma curva simétrica que passa por esses pontos. O estudo conjunto das raízes, do vértice e da concavidade ajuda a interpretar o gráfico sem confundir os coeficientes.']
    ];
    $ids['capitulo']=inserirGemini($conn,'INSERT INTO capitulo_livro(id_livro,ordem,titulo,paginas_json,revisado) VALUES (?,?,?,?,1)',
        [$ids['livro'],1,'Função quadrática',json_encode($paginas,JSON_UNESCAPED_UNICODE)]);
    $ids['conteudo']=inserirGemini($conn,'INSERT INTO conteudo_pedagogico(nome,serie,materia,bimestre,ordem) VALUES (?,?,?,?,?)',
        ['Função quadrática','2º Ano','Matemática',2,1]);
    inserirGemini($conn,'INSERT INTO conteudo_capitulo(id_conteudo,id_capitulo,ordem) VALUES (?,?,0)',
        [$ids['conteudo'],$ids['capitulo']]);
    $token=bin2hex(random_bytes(32));
    inserirGemini($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',
        [$ids['aluno'],$token]);
    $post=['token'=>$token,'id_turma'=>$ids['turma'],'id_conteudo'=>$ids['conteudo'],'id_capitulo'=>$ids['capitulo']];
    [$http,$primeiro]=endpointGemini($post);
    if ($http!==200 || ($primeiro['status'] ?? '')!=='sucesso') throw new RuntimeException('Geração não concluiu: '.($primeiro['mensagem'] ?? 'resposta inválida'));
    $idEstudo=(int)$primeiro['id_estudo'];
    if (count($primeiro['estudo']['questoes'] ?? [])!==5) throw new RuntimeException('Quiz gerado sem cinco questões.');
    [$http,$segundo]=endpointGemini($post);
    if ($http!==200 || (int)($segundo['id_estudo'] ?? 0)!==$idEstudo) throw new RuntimeException('O cache não reutilizou o estudo.');
    echo "OK: Gemini gerou estudo validado com cinco questões e cache reutilizado.\n";
} finally {
    if (isset($ids['capitulo'])) inserirGemini($conn,'DELETE FROM estudo_gerado WHERE id_capitulo=?',[$ids['capitulo']]);
    if (isset($ids['conteudo'])) { inserirGemini($conn,'DELETE FROM conteudo_capitulo WHERE id_conteudo=?',[$ids['conteudo']]); inserirGemini($conn,'DELETE FROM conteudo_pedagogico WHERE id_conteudo=?',[$ids['conteudo']]); }
    if (isset($ids['capitulo'])) inserirGemini($conn,'DELETE FROM capitulo_livro WHERE id_capitulo=?',[$ids['capitulo']]);
    if (isset($ids['livro'])) inserirGemini($conn,'DELETE FROM livro_didatico WHERE id_livro=?',[$ids['livro']]);
    if (isset($ids['aluno'])) { inserirGemini($conn,'DELETE FROM sessao WHERE id_usuario=?',[$ids['aluno']]); inserirGemini($conn,'DELETE FROM matricula WHERE id_aluno=?',[$ids['aluno']]); }
    if (isset($ids['turma'])) inserirGemini($conn,'DELETE FROM turma WHERE id_turma=?',[$ids['turma']]);
    foreach (['aluno','prof'] as $tipo) if (isset($ids[$tipo])) inserirGemini($conn,'DELETE FROM usuario WHERE id_usuario=?',[$ids[$tipo]]);
}
