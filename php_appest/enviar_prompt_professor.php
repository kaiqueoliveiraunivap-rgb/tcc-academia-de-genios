<?php
require_once __DIR__.'/auth.php';
$professor=exigirProfessor($conn);
$idTurma=inteiroDoPost('id_turma');
$materia=is_string($_POST['materia'] ?? null) ? trim($_POST['materia']) : '';
$instrucao=is_string($_POST['instrucao'] ?? null) ? trim($_POST['instrucao']) : '';
$idLivro=inteiroDoPost('id_livro');
$bimestre=inteiroDoPost('bimestre',1,4);
if (isset($_POST['id_livro']) && !$idLivro) responderErro('Livro inválido.',422);
if (isset($_POST['bimestre']) && !$bimestre) responderErro('Bimestre inválido.',422);
if ($bimestre && !$idLivro) responderErro('Selecione o livro que será estudado neste bimestre.',422);
if (isset($_POST['id_aluno'])) responderErro('Atualize o aplicativo: a personalização agora é feita para a turma inteira.',422);
if (!$idTurma || $materia==='' || $instrucao==='') responderErro('Informe a turma, a matéria e a orientação.',422);
if (mb_strlen($instrucao)>1000 || mb_strlen($materia)>100) responderErro('Orientação muito longa. Use até 1.000 caracteres.',422);
$turma=exigirTurmaDoProfessor($conn,$idTurma,$professor['id_usuario']);
$catalogo=$conn->prepare('SELECT 1 FROM livro_didatico l JOIN capitulo_livro c ON c.id_livro=l.id_livro AND c.revisado=1 WHERE l.ativo=1 AND l.materia=? LIMIT 1');
$catalogo->bind_param('s',$materia); $catalogo->execute();
if (!$catalogo->get_result()->fetch_assoc()) responderErro('Esta matéria ainda não possui capítulos disponíveis.',422);
if ($idLivro) {
    $s=$conn->prepare('SELECT titulo FROM livro_didatico WHERE id_livro=? AND materia=? AND ativo=1');
    $s->bind_param('is',$idLivro,$materia); $s->execute(); $livro=$s->get_result()->fetch_assoc();
    if (!$livro) responderErro('O livro não pertence à matéria escolhida.',422);
}

// O servidor monta a orientação; o cliente não controla dificuldade ou fonte.
$promptFinal="Turma: ".$turma['nome_turma'].". Matéria: ".$materia.". Nível médio.".($idLivro ? ' Livro: '.$livro['titulo'].'.' : '').($bimestre ? ' '.$bimestre.'º bimestre.' : '')."\nOrientação do professor: ".$instrucao."\nNo resumo, priorize a explicação dos pontos indicados. No quiz, formule questões que verifiquem esses pontos quando sustentados pelo capítulo.";
try {
    $conn->begin_transaction();
    $s=$conn->prepare('SELECT id_turma FROM turma WHERE id_turma=? AND id_professor=? FOR UPDATE');
    $s->bind_param('ii',$idTurma,$professor['id_usuario']); $s->execute();
    if (!$s->get_result()->fetch_assoc()) throw new RuntimeException('Turma alterada durante o envio.');
    // Reenvios da mesma orientação reutilizam o cache já preparado para a turma.
    $s=$conn->prepare('SELECT id_prompt,instrucao,id_professor,bimestre FROM prompt_professor WHERE id_turma=? AND materia=? AND id_livro <=> ? AND id_aluno IS NULL AND usado=0 ORDER BY id_prompt DESC LIMIT 1');
    $s->bind_param('isi',$idTurma,$materia,$idLivro); $s->execute(); $atual=$s->get_result()->fetch_assoc();
    if ($atual && $atual['instrucao']===$instrucao && (int)$atual['id_professor']===$professor['id_usuario'] && (int)$atual['bimestre']===(int)$bimestre) {
        $idPrompt=(int)$atual['id_prompt'];
    } else {
        $s=$conn->prepare('UPDATE prompt_professor SET usado=1 WHERE id_turma=? AND materia=? AND id_livro <=> ? AND id_aluno IS NULL AND usado=0');
        $s->bind_param('isi',$idTurma,$materia,$idLivro); $s->execute();
        $s=$conn->prepare("INSERT INTO prompt_professor (id_professor,id_aluno,id_turma,materia,id_livro,bimestre,dificuldade,instrucao,prompt_final) VALUES (?,NULL,?,?,?,?, 'MEDIO',?,?)");
        $s->bind_param('iisiiss',$professor['id_usuario'],$idTurma,$materia,$idLivro,$bimestre,$instrucao,$promptFinal); $s->execute();
        $idPrompt=(int)$s->insert_id;
    }
    $conn->commit();
    responderJson(['status'=>'sucesso','id_prompt'=>$idPrompt,'id_turma'=>$idTurma,'mensagem'=>'Orientação aplicada à turma inteira.']);
} catch (Throwable $e) {
    $conn->rollback(); error_log('Orientação da turma: '.$e->getMessage());
    responderErro('Não foi possível salvar a orientação. Tente novamente.',503);
}
