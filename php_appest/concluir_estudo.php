<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
require_once __DIR__.'/ranking_pedagogico.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Quiz exclusivo de alunos.',403);
$id=inteiroDoPost('id_estudo');
$idTurma=inteiroDoPost('id_turma');
$idConteudo=inteiroDoPost('id_conteudo');
$idAtividade=inteiroDoPost('id_atividade');
if ((isset($_POST['id_conteudo']) && !$idConteudo) || (isset($_POST['id_atividade']) && !$idAtividade)) responderErro('Conteúdo ou atividade inválidos.',422);
$tentativa=$_POST['tentativa'] ?? '';
$respostas=json_decode($_POST['respostas'] ?? '',true);
if (!$id || !is_string($tentativa) || !preg_match('/^[a-f0-9-]{36}$/i',$tentativa) || !is_array($respostas) || !array_is_list($respostas) || count($respostas)!==5) responderErro('Resultado inválido.',422);
foreach ($respostas as $r) if (!is_int($r) || $r<0 || $r>3) responderErro('Alternativa inválida.',422);
try {
    $conn->begin_transaction();
    // Serializa conclusões do mesmo aluno para evitar pontos duplicados em reenvios.
    $s=$conn->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? FOR UPDATE'); $s->bind_param('i',$aluno['id_usuario']); $s->execute();
    $s->get_result()->fetch_assoc(); $s->close();
    $s=$conn->prepare('SELECT id_quiz,acertos,total FROM quiz_estudo WHERE id_aluno=? AND tentativa=?'); $s->bind_param('is',$aluno['id_usuario'],$tentativa); $s->execute(); $anterior=$s->get_result()->fetch_assoc();
    if ($anterior) { $conn->commit(); responderJson(['status'=>'sucesso','id_quiz'=>(int)$anterior['id_quiz'],'acertos'=>(int)$anterior['acertos'],'total'=>(int)$anterior['total']]); }
    if ($idTurma) exigirAlunoNaTurma($conn,$aluno['id_usuario'],$idTurma);
    $s=$conn->prepare('SELECT e.*,c.titulo AS capitulo,l.materia,l.ativo FROM estudo_gerado e JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE e.id_estudo=?');
    $s->bind_param('i',$id); $s->execute(); $estudo=$s->get_result()->fetch_assoc();
    if (!$estudo || !$estudo['ativo'] || ($estudo['id_destinatario']!==null && (int)$estudo['id_destinatario']!==$aluno['id_usuario'])) { $conn->rollback(); responderErro('Estudo indisponível para este aluno.',403); }
    if ($estudo['id_turma_destinataria']!==null) {
        $turmaDestinataria=(int)$estudo['id_turma_destinataria'];
        if ($idTurma!==null && $idTurma!==$turmaDestinataria) { $conn->rollback(); responderErro('Este estudo pertence a outra turma.',403); }
        exigirAlunoNaTurma($conn,$aluno['id_usuario'],$turmaDestinataria);
        $idTurma=$turmaDestinataria;
    }
    if ($idAtividade) {
        $s=$conn->prepare('SELECT a.id_conteudo,a.id_turma FROM atividade_publicada a
            JOIN turma t ON t.id_turma=a.id_turma
            JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
            JOIN conteudo_pedagogico cp ON cp.id_conteudo=a.id_conteudo AND cp.serie=t.serie AND cp.ativo=1
            WHERE a.id_atividade=? AND a.ativa=1 LIMIT 1');
        $s->bind_param('ii',$aluno['id_usuario'],$idAtividade); $s->execute();
        $atividade=$s->get_result()->fetch_assoc(); $s->close();
        if (!$atividade || ($idTurma!==null && $idTurma!==(int)$atividade['id_turma']) ||
            ($idConteudo!==null && $idConteudo!==(int)$atividade['id_conteudo'])) {
            $conn->rollback(); responderErro('Atividade indisponível para sua turma.',403);
        }
        $idConteudo=(int)$atividade['id_conteudo'];
        $idTurma=(int)$atividade['id_turma'];
    }
    if ($idConteudo) {
        if (!$idTurma) { $conn->rollback(); responderErro('Informe a turma do conteúdo.',422); }
        $s=$conn->prepare('SELECT 1 FROM conteudo_pedagogico cp
            JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
            JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
            JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
            JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
            JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
            WHERE cp.id_conteudo=? AND cc.id_capitulo=? AND cp.ativo=1 LIMIT 1');
        $s->bind_param('iiii',$idTurma,$aluno['id_usuario'],$idConteudo,$estudo['id_capitulo']);
        $s->execute(); $permitido=$s->get_result()->num_rows===1; $s->close();
        if (!$permitido) { $conn->rollback(); responderErro('Conteúdo indisponível para sua série.',403); }
    } elseif ($idTurma) {
        // Clientes antigos não enviam id_conteudo. Quando há uma única
        // associação válida, recuperamos a identidade pedagógica no servidor.
        $s=$conn->prepare('SELECT cp.id_conteudo FROM conteudo_pedagogico cp
            JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
            JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
            WHERE cc.id_capitulo=? AND cp.ativo=1 LIMIT 2');
        $s->bind_param('ii',$idTurma,$estudo['id_capitulo']); $s->execute();
        $associacoes=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        if (count($associacoes)>1) { $conn->rollback(); responderErro('Atualize o aplicativo para selecionar o conteúdo.',422); }
        if ($associacoes) $idConteudo=(int)$associacoes[0]['id_conteudo'];
    }
    if (!$idConteudo) {
        $s=$conn->prepare('SELECT 1 FROM conteudo_capitulo WHERE id_capitulo=? LIMIT 1');
        $s->bind_param('i',$estudo['id_capitulo']); $s->execute(); $classificado=$s->get_result()->num_rows>0; $s->close();
        if ($classificado) { $conn->rollback(); responderErro('Conteúdo indisponível para sua série.',403); }
    }
    $questoes=json_decode($estudo['conteudo_json'],true,512,JSON_THROW_ON_ERROR)['questoes'];
    if (count($questoes)!==5) throw new RuntimeException('Quiz incompleto.');
    $acertos=0; $total=5;
    foreach ($questoes as $i=>$q) if ($q['alternativas'][$respostas[$i]]['ehCorreta']===true) $acertos++;
    $s=$conn->prepare('INSERT INTO quiz_estudo (id_aluno,id_turma,id_estudo,id_conteudo,tentativa,acertos,total) VALUES (?,?,?,?,?,?,?)');
    $s->bind_param('iiiisii',$aluno['id_usuario'],$idTurma,$id,$idConteudo,$tentativa,$acertos,$total); $s->execute(); $quiz=$s->insert_id;
    if ($idAtividade) {
        $s=$conn->prepare('INSERT INTO atividade_quiz(id_atividade,id_quiz) VALUES (?,?)');
        $s->bind_param('ii',$idAtividade,$quiz); $s->execute(); $s->close();
    }
    $respostaStmt=$conn->prepare('INSERT INTO resposta_estudo (id_quiz,ordem,assunto,acertou,alternativa_escolhida) VALUES (?,?,?,?,?)');
    $historicoStmt=$conn->prepare('INSERT INTO historico (id_aluno,acertou,enunciado_gemini) VALUES (?,?,?)');
    foreach ($questoes as $i=>$q) {
        $ok=$q['alternativas'][$respostas[$i]]['ehCorreta'] ? 1 : 0;
        $assunto=mb_substr(trim($q['assunto'] ?? $estudo['capitulo']),0,255);
        $respostaStmt->bind_param('iisii',$quiz,$i,$assunto,$ok,$respostas[$i]); $respostaStmt->execute();
        $historicoStmt->bind_param('iis',$aluno['id_usuario'],$ok,$q['enunciado']); $historicoStmt->execute();
    }
    $respostaStmt->close(); $historicoStmt->close();
    $s=$conn->prepare('INSERT INTO historico_quiz (id_aluno,materia,acertos,total_questoes) VALUES (?,?,?,?)'); $s->bind_param('isii',$aluno['id_usuario'],$estudo['materia'],$acertos,$total); $s->execute();
    if ($idTurma) atualizarRankingPorMelhor($conn,$aluno['id_usuario'],$idTurma,$idConteudo,(int)$estudo['id_capitulo'],(int)$quiz);
    $conn->commit();
    responderJson(['status'=>'sucesso','id_quiz'=>(int)$quiz,'acertos'=>$acertos,'total'=>$total]);
} catch (Throwable $e) { try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Rollback estudo: '.$rollbackError->getMessage()); } error_log('Conclusão estudo: '.$e->getMessage()); responderErro('Não foi possível salvar o quiz. Tente novamente.',503); }
