<?php
// Integração em banco temporário isolado. Não altera usuários ou resultados do appest.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../estudo_formato.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function verificar($ok,string $mensagem): void { if (!$ok) throw new RuntimeException($mensagem); }
function processoTeste(array $comando,array $ambiente,string $entrada=''): array {
    $p=proc_open($comando,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$ambiente);
    if (!is_resource($p)) throw new RuntimeException('Não foi possível iniciar o teste.');
    fwrite($pipes[0],$entrada); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
    $codigo=proc_close($p);
    verificar($codigo===0,'Subprocesso falhou: '.$err);
    return [$out,$err];
}
function endpointTeste(string $endpoint,array $post,int $esperado=200): array {
    global $ambiente;
    [$out,$err]=processoTeste([PHP_BINARY,__DIR__.'/endpoint_runner.php',$endpoint],$ambiente,json_encode($post,JSON_UNESCAPED_UNICODE));
    preg_match('/HTTP_STATUS=(\d+)/',$err,$m);
    verificar((int)($m[1] ?? 0)===$esperado,$endpoint.' devolveu HTTP inesperado: '.$err.' '.$out);
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
$dbTeste='appest_test_turma_'.bin2hex(random_bytes(5));
$admin=new mysqli(configuracao('DB_HOST','127.0.0.1'),configuracao('DB_USER','root'),configuracao('DB_PASSWORD'),'',(int)configuracao('DB_PORT',3306));
$ambiente=array_merge(getenv(),['DB_NAME'=>$dbTeste,'APP_ENV'=>'production','GEMINI_MODEL'=>'modelo-teste','GEMINI_API_KEY'=>'chave-ficticia-sem-acesso']);
$criado=false;
try {
    $admin->query("CREATE DATABASE `$dbTeste` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $criado=true;
    processoTeste([PHP_BINARY,__DIR__.'/../tools/instalar.php'],$ambiente);
    $admin->select_db($dbTeste); $admin->set_charset('utf8mb4');
    $tokens=[]; $ids=[];
    foreach (['professor','aluno1','aluno2','fora'] as $nome) {
        $perfil=$nome==='professor' ? 'professor' : 'aluno'; $email=$nome.'@teste.invalid'; $senha='fixture-nao-utilizavel';
        $s=$admin->prepare('INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)'); $s->bind_param('ssss',$nome,$email,$senha,$perfil); $s->execute();
        $ids[$nome]=(int)$s->insert_id; $token=bin2hex(random_bytes(32)); $tokens[$nome]=$token;
        $s=$admin->prepare('INSERT INTO sessao(id_usuario,token,expira_em) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))'); $s->bind_param('is',$ids[$nome],$token); $s->execute();
    }
    $admin->query("INSERT INTO turma(nome_turma,ano_letivo,id_professor,serie) VALUES ('Turma teste','2026',".$ids['professor'].",'1ª série')");
    $turma=(int)$admin->insert_id;
    $admin->query("INSERT INTO turma(nome_turma,ano_letivo,id_professor,serie) VALUES ('Outra turma','2026',".$ids['professor'].",'1ª série')");
    $outra=(int)$admin->insert_id;
    $painel=endpointTeste('dashboard_turmas.php',['token'=>$tokens['professor']]);
    verificar(count($painel['turmas'])===2 && $painel['turmas'][0]['serie']==='1ª série','O painel do professor perdeu a série das turmas.');
    foreach (['aluno1'=>$turma,'aluno2'=>$turma,'fora'=>$outra] as $nome=>$t) $admin->query('INSERT INTO matricula(id_aluno,id_turma) VALUES ('.$ids[$nome].','.$t.')');
    $admin->query("INSERT INTO livro_didatico(titulo,materia,fonte_url,versao) VALUES ('Livro teste','Matéria teste','https://example.invalid/livro','teste')");
    $livro=(int)$admin->insert_id;
    $admin->query("INSERT INTO frente_livro(id_livro,ordem,titulo) VALUES ($livro,1,'Frente teste')"); $frente=(int)$admin->insert_id;
    $texto='Uma razão compara duas grandezas por uma divisão. A ordem determina o sentido da comparação e as unidades devem ser observadas antes de calcular. O resultado precisa ser interpretado considerando os valores apresentados e o contexto das grandezas.';
    $paginas=[['pagina'=>1,'texto'=>$texto]]; $paginasJson=json_encode($paginas,JSON_UNESCAPED_UNICODE);
    $s=$admin->prepare("INSERT INTO capitulo_livro(id_livro,id_frente,ordem,titulo,paginas_json,revisado) VALUES (?,?,1,'Razões',?,1)"); $s->bind_param('iis',$livro,$frente,$paginasJson); $s->execute(); $capitulo=(int)$s->insert_id;
    $post=['token'=>$tokens['professor'],'id_turma'=>$turma,'materia'=>'Matéria teste','instrucao'=>'Reforce a interpretação de razões.','dificuldade'=>'DIFICIL'];
    $prompt=endpointTeste('enviar_prompt_professor.php',$post)['id_prompt'];
    verificar(endpointTeste('enviar_prompt_professor.php',$post)['id_prompt']===$prompt,'Reenvio duplicou a orientação.');
    foreach (['aluno1','aluno2'] as $nome) {
        $r=endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens[$nome],'materia'=>'Matéria teste']);
        verificar($r['prompt']['id_prompt']===$prompt && $r['prompt']['dificuldade']==='MEDIO','Orientação não compartilhada em nível médio.');
    }
    endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens['fora'],'id_turma'=>$turma,'materia'=>'Matéria teste'],403);
    verificar(endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens['fora'],'materia'=>'Matéria teste'])['status']==='vazio','Orientação vazou para outra turma.');
    endpointTeste('enviar_prompt_professor.php',array_merge($post,['token'=>$tokens['aluno1']]),403);
    endpointTeste('enviar_prompt_professor.php',array_merge($post,['id_aluno'=>$ids['aluno1']]),422);
    $conteudo=['resumo'=>[],'questoes'=>[],'palavras_chave'=>['Razão','Grandezas','Divisão','Unidades','Comparação']];
    for ($i=0;$i<6;$i++) $conteudo['resumo'][]=['titulo'=>'Conceito '.$i,'texto'=>$texto,'topicos'=>$i<2 ? ['Compare a ordem das grandezas.','Verifique as unidades antes de dividir.'] : [],'pagina'=>1,'trecho'=>$texto];
    for ($i=0;$i<5;$i++) $conteudo['questoes'][]=['enunciado'=>'Exercício '.$i,'explicacao'=>$texto,'pagina'=>1,'trecho'=>$texto,'alternativas'=>[
        ['texto'=>'A','ehCorreta'=>true],['texto'=>'B','ehCorreta'=>false],['texto'=>'C','ehCorreta'=>false],['texto'=>'D','ehCorreta'=>false]
    ]];
    validarEstudoCompleto($conteudo,$paginas); $json=json_encode($conteudo,JSON_UNESCAPED_UNICODE);
    $estudos=[];
    foreach (['turma'=>$prompt,'geral'=>null] as $tipo=>$idPrompt) {
        $versao=versaoEstudoCompleto('modelo-teste',$paginasJson,$idPrompt); $destino=$tipo==='turma' ? $turma : null;
        $s=$admin->prepare("INSERT INTO estudo_gerado(id_capitulo,dificuldade,versao_gerador,conteudo_json,id_turma_destinataria) VALUES (?,'MEDIO',?,?,?)");
        $s->bind_param('issi',$capitulo,$versao,$json,$destino); $s->execute(); $estudos[$tipo]=(int)$s->insert_id;
    }
    foreach (['aluno1'=>'FACIL','aluno2'=>'DIFICIL'] as $nome=>$nivel) {
        $r=endpointTeste('gerar_estudo.php',['token'=>$tokens[$nome],'id_capitulo'=>$capitulo,'dificuldade'=>$nivel]);
        verificar($r['id_estudo']===$estudos['turma'] && $r['dificuldade']==='MEDIO','Quiz médio/cache da turma não respeitado.');
    }
    $r=endpointTeste('gerar_estudo.php',['token'=>$tokens['fora'],'id_capitulo'=>$capitulo]);
    verificar($r['id_estudo']===$estudos['geral'],'Cache personalizado foi compartilhado fora da turma.');
    $resultado=['id_estudo'=>$estudos['turma'],'tentativa'=>'12345678-1234-1234-1234-123456789abc','respostas'=>'[0,0,0,0,0]'];
    verificar(endpointTeste('historico_estudos.php',['token'=>$tokens['aluno1']])['historico']===[],'Histórico inicial deveria estar vazio.');
    verificar(endpointTeste('listar_capitulos.php',['token'=>$tokens['aluno1'],'id_frente'=>$frente])['concluidos']===0,'Capítulo sem quiz apareceu concluído.');
    endpointTeste('concluir_estudo.php',array_merge($resultado,['token'=>$tokens['fora']]),403);
    foreach (['aluno1','aluno2'] as $nome) {
        $r=endpointTeste('concluir_estudo.php',array_merge($resultado,['token'=>$tokens[$nome]]));
        verificar($r['acertos']===5,'Conclusão da turma falhou.');
        $reenvio=endpointTeste('concluir_estudo.php',array_merge($resultado,['token'=>$tokens[$nome]]));
        verificar($r['id_quiz']===$reenvio['id_quiz'],'Reenvio mudou o ID usado para revisão.');
    }
    verificar((int)$admin->query('SELECT COUNT(*) n FROM quiz_estudo')->fetch_assoc()['n']===2,'Reenvio duplicou o quiz.');
    verificar((int)$admin->query('SELECT SUM(pontos) n FROM ranking')->fetch_assoc()['n']===10,'Ranking duplicou pontos.');
    verificar((int)$admin->query("SELECT usado FROM prompt_professor WHERE id_prompt=$prompt")->fetch_assoc()['usado']===0,'O primeiro aluno consumiu a orientação.');
    $novo=endpointTeste('enviar_prompt_professor.php',array_merge($post,['instrucao'=>'Reforce unidades e comparação.']))['id_prompt'];
    verificar($novo!==$prompt,'Nova orientação não criou nova versão.');
    foreach (['aluno1','aluno2'] as $nome) verificar(endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens[$nome],'materia'=>'Matéria teste'])['prompt']['id_prompt']===$novo,'Nova orientação não chegou à turma.');
    // Histórico e revisão são privados do dono da tentativa, mesmo na mesma turma.
    $historico=endpointTeste('historico_estudos.php',['token'=>$tokens['aluno1'],'id_aluno'=>$ids['aluno2']]);
    verificar(count($historico['historico'])===1 && $historico['proximo_cursor']===null,'Histórico ignorou o dono ou a paginação.');
    $quiz=$historico['historico'][0]['id_quiz'];
    $revisao=endpointTeste('revisar_estudo.php',['token'=>$tokens['aluno1'],'id_quiz'=>$quiz])['quiz'];
    verificar(count($revisao['questoes'])===5 && $revisao['questoes'][0]['escolhida']===0 && $revisao['questoes'][0]['acertou']===true,'Alternativa escolhida não foi salva para revisão.');
    verificar($revisao['id_capitulo']===$capitulo && $revisao['pode_refazer']===true,'Revisão perdeu o capítulo para refazer.');
    endpointTeste('revisar_estudo.php',['token'=>$tokens['aluno2'],'id_quiz'=>$quiz],404);
    endpointTeste('revisar_estudo.php',['token'=>$tokens['professor'],'id_quiz'=>$quiz],403);
    endpointTeste('historico_estudos.php',['token'=>$tokens['professor']],403);
    endpointTeste('historico_estudos.php',['token'=>'invalido'],401);
    endpointTeste('historico_estudos.php',['token'=>$tokens['aluno1'],'antes_id'=>-1],422);
    $admin->query("UPDATE resposta_estudo SET alternativa_escolhida=NULL WHERE id_quiz=$quiz AND ordem=0");
    verificar(endpointTeste('revisar_estudo.php',['token'=>$tokens['aluno1'],'id_quiz'=>$quiz])['quiz']['questoes'][0]['escolhida']===null,'Revisão inventou a alternativa de tentativa antiga.');
    // Nova tentativa com erro: deve ter revisão própria e não duplicar o progresso do capítulo.
    $novaTentativa=array_merge($resultado,['token'=>$tokens['aluno1'],'tentativa'=>'22345678-1234-1234-1234-123456789abc','respostas'=>'[1,0,0,0,0]']);
    $segunda=endpointTeste('concluir_estudo.php',$novaTentativa);
    verificar($segunda['acertos']===4 && $segunda['id_quiz']!==$quiz,'Nova tentativa não registrou o resultado separado.');
    $revisao=endpointTeste('revisar_estudo.php',['token'=>$tokens['aluno1'],'id_quiz'=>$segunda['id_quiz']])['quiz'];
    verificar($revisao['questoes'][0]['escolhida']===1 && $revisao['questoes'][0]['acertou']===false,'Erro não foi preservado na revisão.');
    $capitulos=endpointTeste('listar_capitulos.php',['token'=>$tokens['aluno1'],'id_frente'=>$frente]);
    verificar($capitulos['concluidos']===1 && $capitulos['capitulos'][0]['tentativas']===2 && $capitulos['capitulos'][0]['melhor_percentual']==100,'Repetição duplicou o progresso ou perdeu o melhor resultado.');
    $frentes=endpointTeste('listar_frentes.php',['token'=>$tokens['aluno1'],'materia'=>'Matéria teste']);
    verificar($frentes['frentes'][0]['concluidos']===1 && $frentes['frentes'][0]['total_capitulos']===1,'Progresso da frente incorreto.');
    verificar(endpointTeste('listar_capitulos.php',['token'=>$tokens['fora'],'id_frente'=>$frente])['concluidos']===0,'Progresso vazou para outro aluno.');
    $admin->query("UPDATE livro_didatico SET ativo=0 WHERE id_livro=$livro");
    verificar(endpointTeste('revisar_estudo.php',['token'=>$tokens['aluno1'],'id_quiz'=>$quiz])['quiz']['pode_refazer']===false,'Livro retirado deveria manter revisão e bloquear refazer.');
    $admin->query("UPDATE livro_didatico SET ativo=1 WHERE id_livro=$livro");
    // Gráficos calculados a partir das respostas, separados por aluno e por turma.
    $g=endpointTeste('desempenho_estudos.php',['token'=>$tokens['aluno1']]);
    verificar($g['resumo']['respostas']===10 && $g['resumo']['acertos']===9,'Gráfico individual misturou alunos.');
    $g=endpointTeste('desempenho_estudos.php',['token'=>$tokens['professor'],'id_turma'=>$turma]);
    verificar($g['resumo']['respostas']===15 && $g['resumo']['acertos']===14 && $g['itens'][0]['alunos']===2,'Gráfico da turma não agregou os dois alunos.');
    $g=endpointTeste('desempenho_estudos.php',['token'=>$tokens['professor'],'id_turma'=>$turma,'nivel'=>'conteudos','materia'=>'Matéria teste']);
    verificar(count($g['itens'])===1 && $g['itens'][0]['erros']===1,'Detalhamento por conteúdo perdeu erros.');
    verificar(endpointTeste('desempenho_estudos.php',['token'=>$tokens['fora']])['itens']===[],'Gráfico expôs resultados de outro aluno.');
    endpointTeste('desempenho_estudos.php',['token'=>'invalido'],401);
    // Muitas tentativas: cursor sem repetição, inclusive com inserção entre as páginas.
    for ($i=0;$i<25;$i++) {
        $uuid=bin2hex(random_bytes(18));
        $s=$admin->prepare('INSERT INTO quiz_estudo(id_aluno,id_estudo,tentativa,acertos,total) VALUES (?,?,?,1,5)');
        $s->bind_param('iis',$ids['aluno1'],$estudos['geral'],$uuid); $s->execute();
    }
    $pagina1=endpointTeste('historico_estudos.php',['token'=>$tokens['aluno1']]);
    verificar(count($pagina1['historico'])===20 && $pagina1['proximo_cursor']!==null,'Histórico não limitou a página.');
    $uuid=bin2hex(random_bytes(18)); $s->bind_param('iis',$ids['aluno1'],$estudos['geral'],$uuid); $s->execute();
    $pagina2=endpointTeste('historico_estudos.php',['token'=>$tokens['aluno1'],'antes_id'=>$pagina1['proximo_cursor']]);
    verificar(count($pagina2['historico'])===7 && $pagina2['proximo_cursor']===null,'Histórico pulou tentativas ao paginar.');
    verificar(array_intersect(array_column($pagina1['historico'],'id_quiz'),array_column($pagina2['historico'],'id_quiz'))===[],'Histórico repetiu tentativas entre páginas.');
    $ranking=endpointTeste('ranking_serie.php',['token'=>$tokens['aluno1'],'id_turma'=>$turma,'mes_referencia'=>date('Y-m')]);
    verificar(count($ranking['ranking'])===2 && $ranking['ranking'][0]['pontos']===9,'Ranking perdeu pontuação mensal ou duplicou aluno.');
    verificar(endpointTeste('ranking_serie.php',['token'=>$tokens['aluno1'],'id_turma'=>$turma,'mes_referencia'=>'2000-01'])['ranking']===[],'Consulta mensal misturou períodos.');
    endpointTeste('inserir_usuario.php',['nome'=>'Conta indevida','email'=>'indevida@teste.invalid','senha'=>'Senha12345'],403);
    endpointTeste('cadastrar_professor.php',['nome'=>'Conta indevida','email'=>'indevida2@teste.invalid','senha'=>'Senha12345','codigo_convite'=>'abc'],403);
    verificar($admin->query("SELECT 1 FROM usuario WHERE email LIKE 'indevida%@teste.invalid'")->num_rows===0,'Cadastro público ainda criou conta.');
    $livros=endpointTeste('listar_livros_professor.php',['token'=>$tokens['professor'],'materia'=>'Matéria teste'])['livros'];
    verificar((int)$livros[0]['id_livro']===$livro,'Livro da matéria não apareceu para o professor.');
    endpointTeste('listar_livros_professor.php',['token'=>$tokens['aluno1'],'materia'=>'Matéria teste'],403);
    endpointTeste('enviar_prompt_professor.php',array_merge($post,['id_livro'=>$livro,'bimestre'=>5]),422);
    endpointTeste('enviar_prompt_professor.php',array_merge($post,['id_livro'=>$livro+9999]),422);
    $porLivro=endpointTeste('enviar_prompt_professor.php',array_merge($post,['id_livro'=>$livro,'bimestre'=>2]))['id_prompt'];
    verificar($porLivro!==$novo,'Livro específico reutilizou orientação geral.');
    $escolhida=endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens['aluno1'],'materia'=>'Matéria teste','id_livro'=>$livro])['prompt'];
    verificar($escolhida['id_prompt']===$porLivro && (int)$escolhida['bimestre']===2,'Orientação do livro/bimestre não ganhou prioridade.');
    verificar(endpointTeste('buscar_prompt_pendente.php',['token'=>$tokens['aluno1'],'materia'=>'Matéria teste'])['prompt']['id_prompt']===$novo,'Orientação geral foi substituída ao escolher livro.');
    echo "OK: histórico paginado, revisão privada, alternativas antigas, refazer, progresso distinto e ranking mensal.\n";
    echo "OK: 2 alunos compartilham a orientação e o quiz médio; outra turma não acessa; atualização e reenvios preservam resultados.\n";
    echo "OK: só a secretaria cadastra; professor filtra livro e bimestre; orientação específica precede a geral.\n";
} finally {
    // Somente o banco aleatório criado por este processo pode ser removido.
    if ($criado && preg_match('/^appest_test_turma_[a-f0-9]{10}$/',$dbTeste)) $admin->query("DROP DATABASE `$dbTeste`");
}
