<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$usuario=exigirUsuarioLogado($conn);
$perfil=normalizarPerfil($usuario['tipo_perfil']);
if (!in_array($perfil,['aluno','professor'],true)) responderErro('Área de alunos e professores.',403);
$idUsuario=$usuario['id_usuario'];
$acao=$_POST['acao'] ?? 'contexto';
try {
    if ($acao==='contexto') {
        $sql=$perfil==='professor'
            ? 'SELECT id_turma,nome_turma,ano_letivo,serie FROM turma WHERE id_professor=? ORDER BY serie,nome_turma'
            : 'SELECT t.id_turma,t.nome_turma,t.ano_letivo,t.serie FROM turma t JOIN matricula m ON m.id_turma=t.id_turma WHERE m.id_aluno=? ORDER BY t.serie,t.nome_turma';
        $s=$conn->prepare($sql); $s->bind_param('i',$idUsuario); $s->execute();
        $turmas=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        $series=[];
        if ($perfil==='professor') {
            $r=$conn->query("SELECT DISTINCT serie FROM turma WHERE serie<>'' ORDER BY serie");
            while ($x=$r->fetch_assoc()) $series[]=$x['serie'];
        } else foreach ($turmas as $t) if ($t['serie']!=='' && !in_array($t['serie'],$series,true)) $series[]=$t['serie'];
        $r=$conn->query("SELECT nome AS materia FROM materia UNION SELECT materia FROM livro_didatico WHERE ativo=1 ORDER BY materia");
        $materias=array_column($r->fetch_all(MYSQLI_ASSOC),'materia');
        responderJson(['status'=>'sucesso','turmas'=>$turmas,'series'=>$series,'materias'=>$materias]);
    }
    if ($acao==='serie_turma') {
        if ($perfil!=='professor') responderErro('Acesso apenas para professores.',403);
        $id=inteiroDoPost('id_turma');
        exigirTurmaDoProfessor($conn,$id ?? 0,$idUsuario);
        $serie=trim((string)($_POST['serie'] ?? ''));
        if ($serie==='' || mb_strlen($serie)>80) responderErro('Informe a série escolar da turma.',422);
        $s=$conn->prepare('UPDATE turma SET serie=? WHERE id_turma=?'); $s->bind_param('si',$serie,$id); $s->execute();
        responderJson(['status'=>'sucesso']);
    }
    if ($acao==='ranking') {
        $serie=trim((string)($_POST['serie'] ?? ''));
        $mes=$_POST['mes'] ?? date('Y-m');
        if ($serie==='' || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$mes)) responderErro('Série ou mês inválidos.',422);
        if ($perfil==='aluno') {
            $s=$conn->prepare('SELECT 1 FROM matricula m JOIN turma t ON t.id_turma=m.id_turma WHERE m.id_aluno=? AND t.serie=? LIMIT 1');
            $s->bind_param('is',$idUsuario,$serie); $s->execute();
            if ($s->get_result()->num_rows!==1) responderErro('Você pode ver apenas o ranking da sua série.',403);
        }
        // Escola única neste projeto: todas as turmas da série, com um aluno por posição.
        $s=$conn->prepare('SELECT u.id_usuario,u.nome,SUM(r.pontos) AS pontos FROM ranking r JOIN usuario u ON u.id_usuario=r.id_aluno JOIN turma t ON t.id_turma=r.id_turma WHERE t.serie=? AND r.mes_referencia=? GROUP BY u.id_usuario,u.nome ORDER BY pontos DESC,u.nome,u.id_usuario');
        $s->bind_param('ss',$serie,$mes); $s->execute(); $dados=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($dados as &$d) { $d['pontos']=(int)$d['pontos']; $d['proprio']=(int)$d['id_usuario']===$idUsuario; } unset($d);
        responderJson(['status'=>'sucesso','ranking'=>$dados,'serie'=>$serie,'mes'=>$mes]);
    }
    if ($acao==='desempenho') {
        $materia=trim((string)($_POST['materia'] ?? ''));
        if ($perfil==='professor') {
            $idTurma=inteiroDoPost('id_turma');
            exigirTurmaDoProfessor($conn,$idTurma ?? 0,$idUsuario);
            if ($materia==='') responderErro('Selecione sua matéria.',422);
            $where='q.id_turma=? AND l.materia=?'; $val=$idTurma;
        } else { $where='q.id_aluno=? AND (?=\'\' OR l.materia=?)'; $val=$idUsuario; }
        $sql="SELECT l.materia,r.assunto,COUNT(*) AS respostas,SUM(r.acertou) AS acertos,COUNT(DISTINCT q.id_aluno) AS alunos FROM resposta_estudo r JOIN quiz_estudo q ON q.id_quiz=r.id_quiz JOIN estudo_gerado e ON e.id_estudo=q.id_estudo JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE $where GROUP BY l.materia,r.assunto ORDER BY l.materia,r.assunto";
        $s=$conn->prepare($sql);
        if ($perfil==='professor') $s->bind_param('is',$val,$materia); else $s->bind_param('iss',$val,$materia,$materia);
        $s->execute(); $assuntos=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($assuntos as &$a) {
            $a['acertos']=(int)$a['acertos']; $a['respostas']=(int)$a['respostas']; $a['alunos']=(int)$a['alunos'];
            $a['percentual']=round(100*$a['acertos']/$a['respostas'],1);
        } unset($a);
        // Evolução usa o histórico de quizzes, incluindo os resultados anteriores.
        $evolucao=[];
        if ($perfil==='aluno') {
            $s=$conn->prepare("SELECT materia,acertos,total_questoes,DATE_FORMAT(data_realizacao,'%d/%m/%Y') AS data FROM historico_quiz WHERE id_aluno=? AND (?='' OR materia=?) ORDER BY id_historico");
            $s->bind_param('iss',$idUsuario,$materia,$materia); $s->execute(); $evolucao=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        responderJson(['status'=>'sucesso','assuntos'=>$assuntos,'evolucao'=>$evolucao]);
    }
    responderErro('Ação inválida.',422);
} catch (Throwable $e) { error_log('Portal: '.$e->getMessage()); responderErro('Não foi possível carregar os dados. Tente novamente.',503); }
