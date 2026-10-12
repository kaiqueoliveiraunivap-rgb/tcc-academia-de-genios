<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Quiz exclusivo de alunos.',403);
$id=inteiroDoPost('id_estudo');
$idTurma=inteiroDoPost('id_turma');
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
    $questoes=json_decode($estudo['conteudo_json'],true,512,JSON_THROW_ON_ERROR)['questoes'];
    if (count($questoes)!==5) throw new RuntimeException('Quiz incompleto.');
    $acertos=0; $total=5;
    foreach ($questoes as $i=>$q) if ($q['alternativas'][$respostas[$i]]['ehCorreta']===true) $acertos++;
    $s=$conn->prepare('INSERT INTO quiz_estudo (id_aluno,id_turma,id_estudo,tentativa,acertos,total) VALUES (?,?,?,?,?,?)');
    $s->bind_param('iiisii',$aluno['id_usuario'],$idTurma,$id,$tentativa,$acertos,$total); $s->execute(); $quiz=$s->insert_id;
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
    if ($idTurma) {
        $mes=date('Y-m');
        $s=$conn->prepare('INSERT INTO ranking (id_aluno,id_turma,pontos,mes_referencia) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE pontos=pontos+VALUES(pontos)');
        $s->bind_param('iiis',$aluno['id_usuario'],$idTurma,$acertos,$mes); $s->execute();
    }
    $conn->commit();
    responderJson(['status'=>'sucesso','id_quiz'=>(int)$quiz,'acertos'=>$acertos,'total'=>$total]);
} catch (Throwable $e) { try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Rollback estudo: '.$rollbackError->getMessage()); } error_log('Conclusão estudo: '.$e->getMessage()); responderErro('Não foi possível salvar o quiz. Tente novamente.',503); }
