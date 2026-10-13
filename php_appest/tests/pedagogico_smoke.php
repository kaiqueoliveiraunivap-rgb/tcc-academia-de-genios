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
$pasta=substr($base,0,strrpos($base,'/')+1);
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
function coordenacao(string $url,string $metodo,array $dados,string $cookie,string $csrf=''): array {
    $url.=$metodo==='GET' ? '?'.http_build_query($dados) : '';
    $headers="Cookie: academia_coord=$cookie\r\nX-Coord-Request: 1\r\n";
    if ($metodo==='POST') $headers.="Content-Type: application/json\r\nX-CSRF-Token: $csrf\r\n";
    $context=stream_context_create(['http'=>[
        'method'=>$metodo,'header'=>$headers,'content'=>$metodo==='POST' ? json_encode($dados) : '',
        'ignore_errors'=>true,'timeout'=>8
    ]]);
    $corpo=file_get_contents($url,false,$context);
    if ($corpo===false) throw new RuntimeException('Painel de teste indisponível.');
    preg_match('/HTTP\/\S+\s+(\d+)/',$http_response_header[0] ?? '',$m);
    return [(int)($m[1] ?? 0),json_decode($corpo,true,512,JSON_THROW_ON_ERROR)];
}
function confirmar(bool $ok,string $mensagem): void {
    if (!$ok) throw new RuntimeException($mensagem);
}
try {
    $sufixo=bin2hex(random_bytes(5)); $senha=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
    $ids['prof']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Professor Teste $sufixo","prof-$sufixo@example.invalid",$senha,'professor']);
    $ids['admin']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Secretaria Teste $sufixo","secretaria-$sufixo@example.invalid",$senha,'admin']);
    $ids['alunoA']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Aluno A $sufixo","aluno-a-$sufixo@example.invalid",$senha,'aluno']);
    $ids['alunoB']=gravar($conn,'INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',["Aluno B $sufixo","aluno-b-$sufixo@example.invalid",$senha,'aluno']);
    $ids['turmaA']=gravar($conn,'INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',["2º Ano A $sufixo",'2º Ano','2026',$ids['prof']]);
    $ids['turmaB']=gravar($conn,'INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',["3º Ano A $sufixo",'3º Ano','2026',$ids['prof']]);
    gravar($conn,'INSERT INTO matricula(id_turma,id_aluno) VALUES (?,?)',[$ids['turmaA'],$ids['alunoA']]);
    gravar($conn,'INSERT INTO matricula(id_turma,id_aluno) VALUES (?,?)',[$ids['turmaB'],$ids['alunoB']]);
    $ids['livro']=gravar($conn,'INSERT INTO livro_didatico(titulo,materia,fonte_url,versao,ativo) VALUES (?,?,?,?,1)',["Livro teste $sufixo",'Matemática','https://example.invalid/livro.pdf',hash('sha256',$sufixo)]);
    $ids['capitulo']=gravar($conn,'INSERT INTO capitulo_livro(id_livro,ordem,titulo,paginas_json,revisado) VALUES (?,?,?,?,1)',[$ids['livro'],1,'Funções quadráticas',json_encode([['pagina'=>1,'texto'=>'Exemplo de função quadrática.']])]);
    $coordToken=bin2hex(random_bytes(32)); $csrf=bin2hex(random_bytes(32));
    gravar($conn,'INSERT INTO coordenacao_sessao(token_hash,id_usuario,csrf,expira_em,ultimo_acesso) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())',
        [hash('sha256',$coordToken),$ids['admin'],$csrf]);
    $coord=$pasta.'coordenacao/api.php';
    [$codigo,$semCsrf]=coordenacao($coord,'POST',['acao'=>'salvar_conteudo'], $coordToken,'');
    confirmar($codigo===403,'Painel aceitou alteração sem CSRF.');
    [$codigo,$salvo]=coordenacao($coord,'POST',['acao'=>'salvar_conteudo','nome'=>'Função quadrática',
        'serie'=>'2º Ano','materia'=>'Matemática','bimestre'=>2,'ordem'=>1,
        'ativo'=>true,'capitulos'=>[$ids['capitulo']]],$coordToken,$csrf);
    confirmar($codigo===200 && $salvo['id']>0,'Secretaria não classificou capítulo.');
    $ids['conteudo']=$salvo['id'];
    [$codigo,$catalogo]=coordenacao($coord,'GET',['acao'=>'conteudos'],$coordToken);
    confirmar($codigo===200 && count($catalogo['itens'])===1 &&
        $catalogo['itens'][0]['bimestre']==2,'Catálogo da secretaria não refletiu a classificação.');
    $questoes=[];
    for ($i=0;$i<5;$i++) $questoes[]=['enunciado'=>'Questão '.($i+1),'assunto'=>'Função quadrática',
        'alternativas'=>[['ehCorreta'=>true],['ehCorreta'=>false],['ehCorreta'=>false],['ehCorreta'=>false]]];
    $ids['estudo']=gravar($conn,'INSERT INTO estudo_gerado(id_capitulo,dificuldade,versao_gerador,conteudo_json) VALUES (?,?,?,?)',
        [$ids['capitulo'],'MEDIO','teste-'.$sufixo,json_encode(['questoes'=>$questoes])]);
    $tokenA=bin2hex(random_bytes(32)); $tokenB=bin2hex(random_bytes(32)); $tokenProf=bin2hex(random_bytes(32));
    gravar($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',[$ids['alunoA'],$tokenA]);
    gravar($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',[$ids['alunoB'],$tokenB]);
    gravar($conn,'INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))',[$ids['prof'],$tokenProf]);

    $prof=$pasta.'professor_pedagogico.php';
    [$codigo,$resumoProf]=chamar($prof,['token'=>$tokenProf,'acao'=>'resumo']);
    confirmar($codigo===200 && $resumoProf['turmas_total']===2 && $resumoProf['alunos_total']===2,'Resumo do professor incorreto.');
    [$codigo]=chamar($pasta.'ranking_serie.php',['token'=>$tokenProf,'serie'=>'1º Ano',
        'mes_referencia'=>date('Y-m')]);
    confirmar($codigo===403,'Professor consultou ranking de série sem vínculo.');
    [$codigo,$catalogoProf]=chamar($prof,['token'=>$tokenProf,'acao'=>'conteudos','id_turma'=>$ids['turmaA']]);
    confirmar($codigo===200 && count($catalogoProf['conteudos'])===1,'Professor não recebeu conteúdos da turma.');
    [$codigo]=chamar($prof,['token'=>$tokenA,'acao'=>'conteudos','id_turma'=>$ids['turmaA']]);
    confirmar($codigo===403,'Aluno entrou na API do professor.');
    $escopo=['token'=>$tokenProf,'id_turma'=>$ids['turmaA'],'id_conteudo'=>$ids['conteudo']];
    [$codigo,$orientacao]=chamar($prof,$escopo+['acao'=>'salvar_orientacao','instrucao'=>'Explique com exemplos resolvidos.']);
    confirmar($codigo===200 && $orientacao['id_prompt']>0,'Professor não salvou orientação.');
    [$codigo,$detalhe]=chamar($prof,$escopo+['acao'=>'detalhe']);
    confirmar($codigo===200 && $detalhe['orientacao']['instrucao']==='Explique com exemplos resolvidos.','Orientação ativa não apareceu.');
    [$codigo,$publicacao]=chamar($prof,$escopo+['acao'=>'publicar_atividade','titulo'=>'Prática de funções']);
    confirmar($codigo===200 && $publicacao['id_atividade']>0,'Professor não publicou atividade.');
    $ids['atividade']=$publicacao['id_atividade'];

    [$codigo]=chamar($base,['token'=>'invalido']); confirmar($codigo===401,'Token inválido não foi recusado.');
    [$codigo]=chamar($pasta.'ler_capitulo.php',['token'=>$tokenB,'id_capitulo'=>$ids['capitulo']]);
    confirmar($codigo===403,'Leitura de capítulo de outra série não foi bloqueada.');
    [$codigo]=chamar($pasta.'gerar_estudo.php',['token'=>$tokenB,'id_capitulo'=>$ids['capitulo'],'id_turma'=>$ids['turmaB']]);
    confirmar($codigo===403,'Geração de capítulo de outra série não foi bloqueada.');
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
    [$codigo,$painel]=chamar($pasta.'painel_aluno.php',['token'=>$tokenA]);
    confirmar($codigo===200 && $painel['conteudos_disponiveis']===1 &&
        $painel['continuar']['id_conteudo']===$ids['conteudo'] &&
        $painel['aproveitamento_medio']===null && $painel['atividades_pendentes']===1,
        'Dashboard inicial apresentou indicadores incorretos.');
    [$codigo,$atv]=chamar($pasta.'atividades_aluno.php',['token'=>$tokenA]);
    confirmar($codigo===200 && count($atv['atividades'])===1 && $atv['atividades'][0]['estado']==='em_andamento','Atividade não apareceu.');
    [$codigo]=chamar($pasta.'atividades_aluno.php',['token'=>$tokenB,'acao'=>'abrir','id_atividade'=>$ids['atividade']]);
    confirmar($codigo===403,'Aluno de outra turma abriu atividade.');
    $quiz=$pasta.'concluir_estudo.php';
    [$codigo]=chamar($quiz,['token'=>$tokenB,'id_estudo'=>$ids['estudo'],'id_turma'=>$ids['turmaB'],
        'tentativa'=>'99999999-9999-4999-8999-'.substr(hash('sha256',$sufixo),0,12),
        'respostas'=>json_encode([0,0,0,0,0])]);
    confirmar($codigo===403,'Quiz de outra série não foi bloqueado.');
    $dados=['token'=>$tokenA,'id_estudo'=>$ids['estudo'],'id_conteudo'=>$ids['conteudo'],'id_turma'=>$ids['turmaA']];
    $dados['tentativa']='11111111-1111-4111-8111-'.substr(hash('sha256',$sufixo),0,12);
    $dados['respostas']=json_encode([0,0,1,1,1]);
    $dados['acertos']=5; // Valor forjado pelo cliente não pode definir a pontuação.
    [$codigo,$r1]=chamar($quiz,$dados); confirmar($codigo===200 && $r1['acertos']===2,'Primeiro quiz falhou.');
    [$codigo,$reenvio]=chamar($quiz,$dados); confirmar($codigo===200 && $reenvio['id_quiz']===$r1['id_quiz'],'Reenvio criou outra tentativa.');
    $dados['tentativa']='22222222-2222-4222-8222-'.substr(hash('sha256',$sufixo),0,12);
    $dados['respostas']=json_encode([0,0,0,0,1]);
    [$codigo,$r2]=chamar($quiz,$dados); confirmar($codigo===200 && $r2['acertos']===4,'Segunda tentativa falhou.');
    $dados['tentativa']='33333333-3333-4333-8333-'.substr(hash('sha256',$sufixo),0,12);
    $dados['respostas']=json_encode([0,0,0,0,0]); $dados['id_atividade']=$ids['atividade'];
    [$codigo,$r3]=chamar($quiz,$dados); confirmar($codigo===200 && $r3['acertos']===5,'Quiz da atividade falhou.');
    $pontos=(int)$conn->query('SELECT pontos FROM ranking WHERE id_aluno='.$ids['alunoA'].' AND id_turma='.$ids['turmaA'])->fetch_assoc()['pontos'];
    confirmar($pontos===5,'Ranking somou tentativas ou atividade repetida: '.$pontos);
    [$codigo]=coordenacao($coord,'POST',['acao'=>'salvar_conteudo','id'=>$ids['conteudo'],
        'nome'=>'Função quadrática','serie'=>'3º Ano','materia'=>'Matemática','bimestre'=>2,
        'ordem'=>1,'ativo'=>true,'capitulos'=>[$ids['capitulo']]],$coordToken,$csrf);
    confirmar($codigo===409,'Secretaria alterou a série de um conteúdo com histórico.');
    [$codigo,$atv]=chamar($pasta.'atividades_aluno.php',['token'=>$tokenA]);
    confirmar($codigo===200 && $atv['atividades'][0]['estado']==='concluida','Atividade não foi concluída.');
    [$codigo,$painel]=chamar($pasta.'painel_aluno.php',['token'=>$tokenA]);
    confirmar($codigo===200 && $painel['conteudos_concluidos']===1 &&
        $painel['quizzes_realizados']===3 && $painel['atividades_pendentes']===0,
        'Dashboard não refletiu os quizzes ou a atividade concluída.');
    [$codigo,$detalhe]=chamar($prof,$escopo+['acao'=>'detalhe']);
    confirmar($codigo===200 && $detalhe['desempenho']['alunos']===1 &&
        $detalhe['desempenho']['respostas']===15 && $detalhe['atividades'][0]['concluintes']===1,
        'Relatório do professor não refletiu respostas reais.');
    $ids['capitulo2']=gravar($conn,'INSERT INTO capitulo_livro(id_livro,ordem,titulo,paginas_json,revisado) VALUES (?,?,?,?,1)',
        [$ids['livro'],2,'Gráficos da função',json_encode([['pagina'=>2,'texto'=>'Exemplo de gráfico.']])]);
    gravar($conn,'INSERT INTO conteudo_capitulo(id_conteudo,id_capitulo,ordem) VALUES (?,?,1)',[$ids['conteudo'],$ids['capitulo2']]);
    [$codigo,$aberto]=chamar($base,['token'=>$tokenA,'acao'=>'abrir','id_conteudo'=>$ids['conteudo']]);
    confirmar($codigo===200 && count($aberto['conteudo']['unidades'])===2,'Conteúdo com duas unidades não foi listado.');
    [$codigo,$avancou]=chamar($base,['token'=>$tokenA,'acao'=>'avancar','id_conteudo'=>$ids['conteudo'],
        'id_capitulo'=>$ids['capitulo2']]);
    confirmar($codigo===200 && $avancou['conteudo']['id_capitulo']===$ids['capitulo2'] &&
        $avancou['conteudo']['unidade_atual']===2,'Avanço da unidade não persistiu.');
    [$codigo,$retomado]=chamar($base,['token'=>$tokenA,'acao'=>'abrir','id_conteudo'=>$ids['conteudo']]);
    confirmar($codigo===200 && $retomado['conteudo']['id_capitulo']===$ids['capitulo2'],'Retomada da segunda unidade falhou.');
    [$codigo]=chamar($base,['token'=>$tokenB,'acao'=>'avancar','id_conteudo'=>$ids['conteudo'],
        'id_capitulo'=>$ids['capitulo2']]);
    confirmar($codigo===403,'Aluno de outra série avançou a unidade.');
    echo "OK: aluno, professor, orientação, atividade, quiz idempotente, relatório e ranking.\n";
} finally {
    if (isset($ids['alunoA'])) {
        gravar($conn,'DELETE FROM resposta_estudo WHERE id_quiz IN (SELECT id_quiz FROM quiz_estudo WHERE id_aluno=?)',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM atividade_quiz WHERE id_quiz IN (SELECT id_quiz FROM quiz_estudo WHERE id_aluno=?)',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM quiz_estudo WHERE id_aluno=?',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM historico WHERE id_aluno=?',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM historico_quiz WHERE id_aluno=?',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM ranking_melhor_conteudo WHERE id_aluno=?',[$ids['alunoA']]);
        gravar($conn,'DELETE FROM ranking WHERE id_aluno=?',[$ids['alunoA']]);
    }
    if (isset($ids['atividade'])) gravar($conn,'DELETE FROM atividade_publicada WHERE id_atividade=?',[$ids['atividade']]);
    if (isset($ids['conteudo'])) gravar($conn,'DELETE FROM prompt_professor WHERE id_conteudo=?',[$ids['conteudo']]);
    if (isset($ids['estudo'])) gravar($conn,'DELETE FROM estudo_gerado WHERE id_estudo=?',[$ids['estudo']]);
    if (isset($ids['alunoA'],$ids['conteudo'])) gravar($conn,'DELETE FROM progresso_conteudo WHERE id_aluno=? AND id_conteudo=?',[$ids['alunoA'],$ids['conteudo']]);
    if (isset($ids['conteudo'])) { gravar($conn,'DELETE FROM conteudo_capitulo WHERE id_conteudo=?',[$ids['conteudo']]); gravar($conn,'DELETE FROM conteudo_pedagogico WHERE id_conteudo=?',[$ids['conteudo']]); }
    if (isset($ids['capitulo'])) gravar($conn,'DELETE FROM capitulo_livro WHERE id_capitulo=?',[$ids['capitulo']]);
    if (isset($ids['capitulo2'])) gravar($conn,'DELETE FROM capitulo_livro WHERE id_capitulo=?',[$ids['capitulo2']]);
    if (isset($ids['livro'])) gravar($conn,'DELETE FROM livro_didatico WHERE id_livro=?',[$ids['livro']]);
    foreach (['alunoA','alunoB'] as $aluno) if (isset($ids[$aluno])) { gravar($conn,'DELETE FROM sessao WHERE id_usuario=?',[$ids[$aluno]]); gravar($conn,'DELETE FROM matricula WHERE id_aluno=?',[$ids[$aluno]]); }
    if (isset($ids['prof'])) gravar($conn,'DELETE FROM sessao WHERE id_usuario=?',[$ids['prof']]);
    if (isset($ids['admin'])) {
        gravar($conn,'DELETE FROM coordenacao_sessao WHERE id_usuario=?',[$ids['admin']]);
        gravar($conn,'DELETE FROM coordenacao_auditoria WHERE id_autor=?',[$ids['admin']]);
    }
    foreach (['turmaA','turmaB'] as $turma) if (isset($ids[$turma])) gravar($conn,'DELETE FROM turma WHERE id_turma=?',[$ids[$turma]]);
    foreach (['prof','admin','alunoA','alunoB'] as $usuario) if (isset($ids[$usuario])) gravar($conn,'DELETE FROM usuario WHERE id_usuario=?',[$ids[$usuario]]);
}
