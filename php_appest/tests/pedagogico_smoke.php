<?php
// Teste HTTP com dados temporários, exclusivamente no banco isolado indicado.
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../config.php';
if (configuracao('DB_NAME')!=='academia_refatoracao_test') {
    fwrite(STDERR,"Use DB_NAME=academia_refatoracao_test.\n"); exit(1);
}
require __DIR__.'/../conexao.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$base=getenv('PEDAGOGICO_TEST_URL') ?: 'http://127.0.0.1:8089/php_appest/catalogo_estudos.php';
$ids=[];
function gravar(mysqli $conn,string $sql,array $args=[]): int {
    $s=$conn->prepare($sql);
    if ($args) $s->bind_param(str_repeat('s',count($args)),...$args);
    $s->execute(); $id=(int)$s->insert_id; $s->close(); return $id;
}
function chamar(string $url,array $dados): array {
    $context=stream_context_create(['http'=>[
        'method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded',
        'content'=>http_build_query($dados),'ignore_errors'=>true,'timeout'=>8
    ]]);
    $corpo=file_get_contents($url,false,$context);
    if ($corpo===false) throw new RuntimeException('API de teste indisponível.');
    preg_match('/HTTP\/\S+\s+(\d+)/',$http_response_header[0] ?? '',$m);
    return [(int)($m[1] ?? 0),json_decode($corpo,true,512,JSON_THROW_ON_ERROR)];
}
function confirmar(bool $ok,string $mensagem): void {
    if (!$ok) throw new RuntimeException($mensagem);
}
try {
    $sufixo=bin2hex(random_bytes(5)); $senha=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
    $ids['prof']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Professor Teste $sufixo","prof-$sufixo@example.invalid",$senha,'professor']);
    $ids['alunoA']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Aluno A $sufixo","aluno-a-$sufixo@example.invalid",$senha,'aluno']);
    $ids['alunoB']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Aluno B $sufixo","aluno-b-$sufixo@example.invalid",$senha,'aluno']);
    $ids['turmaA']=gravar($conn,'INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',["2º Ano A $sufixo",'2º Ano','2026',$ids['prof']]);
    $ids['turmaB']=gravar($conn,'INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',["3º Ano A $sufixo",'3º Ano','2026',$ids['prof']]);
    gravar($conn,'INSERT INTO matricula(id_turma,id_aluno) VALUES (?,?)',[$ids['turmaA'],$ids['alunoA']]);
    gravar($conn,'INSERT INTO matricula(id_turma,id_aluno) VALUES (?,?)',[$ids['turmaB'],$ids['alunoB']]);
    $ids['livro']=gravar($conn,'INSERT INTO livro_didatico(titulo,materia,fonte_url,versao,ativo) VALUES (?,?,?,?,1)',["Livro teste $sufixo",'Matemática','https://example.invalid/livro.pdf',hash('sha256',$sufixo)]);
    $ids['capitulo']=gravar($conn,'INSERT INTO capitulo_livro(id_livro,ordem,titulo,paginas_json,revisado) VALUES (?,?,?,?,1)',[$ids['livro'],1,'Funções quadráticas',json_encode([['pagina'=>1,'texto'=>'Exemplo de função quadrática.']])]);
    $ids['conteudo']=gravar($conn,'INSERT INTO conteudo_pedagogico(nome,serie,materia,bimestre,ordem) VALUES (?,?,?,?,?)',['Função quadrática','2º Ano','Matemática',2,1]);
    gravar($conn,'INSERT INTO conteudo_capitulo(id_conteudo,id_capitulo) VALUES (?,?)',[$ids['conteudo'],$ids['capitulo']]);
    $tokenA=bin2hex(random_bytes(32)); $tokenB=bin2hex(random_bytes(32));
    gravar($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',[$ids['alunoA'],$tokenA]);
    gravar($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',[$ids['alunoB'],$tokenB]);

    [$codigo]=chamar($base,['token'=>'invalido']); confirmar($codigo===401,'Token inválido não foi recusado.');
    [$codigo,$a]=chamar($base,['token'=>$tokenA]);
    confirmar($codigo===200 && count($a['materias'])===1 && $a['materias'][0]['total']===1,'Matéria da série A não apareceu.');
    [$codigo,$b]=chamar($base,['token'=>$tokenB]);
    confirmar($codigo===200 && count($b['materias'])===0,'Aluno da série B viu conteúdo da A.');
    [$codigo,$lista]=chamar($base,['token'=>$tokenA,'acao'=>'conteudos','materia'=>'Matemática']);
    confirmar($codigo===200 && $lista['conteudos'][0]['estado']==='nao_iniciado' && $lista['conteudos'][0]['bimestre']===2,'Estado ou bimestre inicial incorreto.');
    [$codigo]=chamar($base,['token'=>$tokenB,'acao'=>'abrir','id_conteudo'=>$ids['conteudo']]);
    confirmar($codigo===403,'Abertura entre séries não foi bloqueada.');
    [$codigo,$aberto]=chamar($base,['token'=>$tokenA,'acao'=>'abrir','id_conteudo'=>$ids['conteudo']]);
    confirmar($codigo===200 && $aberto['conteudo']['id_capitulo']===$ids['capitulo'],'Abertura do conteúdo falhou.');
    [$codigo,$lista]=chamar($base,['token'=>$tokenA,'acao'=>'conteudos','materia'=>'Matemática']);
    confirmar($codigo===200 && $lista['conteudos'][0]['estado']==='em_andamento','Progresso iniciado não persistiu.');
    echo "OK: matrícula, série, bimestre, autorização e retomada.\n";
} finally {
    if (isset($ids['alunoA'],$ids['conteudo'])) gravar($conn,'DELETE FROM progresso_conteudo WHERE id_aluno=? AND id_conteudo=?',[$ids['alunoA'],$ids['conteudo']]);
    if (isset($ids['conteudo'])) { gravar($conn,'DELETE FROM conteudo_capitulo WHERE id_conteudo=?',[$ids['conteudo']]); gravar($conn,'DELETE FROM conteudo_pedagogico WHERE id_conteudo=?',[$ids['conteudo']]); }
    if (isset($ids['capitulo'])) gravar($conn,'DELETE FROM capitulo_livro WHERE id_capitulo=?',[$ids['capitulo']]);
    if (isset($ids['livro'])) gravar($conn,'DELETE FROM livro_didatico WHERE id_livro=?',[$ids['livro']]);
    foreach (['alunoA','alunoB'] as $aluno) if (isset($ids[$aluno])) { gravar($conn,'DELETE FROM sessao WHERE id_usuario=?',[$ids[$aluno]]); gravar($conn,'DELETE FROM matricula WHERE id_aluno=?',[$ids[$aluno]]); }
    foreach (['turmaA','turmaB'] as $turma) if (isset($ids[$turma])) gravar($conn,'DELETE FROM turma WHERE id_turma=?',[$ids[$turma]]);
    foreach (['prof','alunoA','alunoB'] as $usuario) if (isset($ids[$usuario])) gravar($conn,'DELETE FROM usuario WHERE id_usuario=?',[$ids[$usuario]]);
}
