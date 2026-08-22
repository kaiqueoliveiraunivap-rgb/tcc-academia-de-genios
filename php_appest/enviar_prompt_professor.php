<?php
// Salva a orientação que o professor escreveu para um aluno específico.
// O "prompt_final" já vem pronto do app (montado por PromptProfessorBuilder.kt),
// no formato que a IA entende melhor; "instrucao" é o texto original do professor.
//
// Este é o endpoint mais sensível do fluxo do professor: o texto gravado aqui
// entra depois no prompt enviado à Gemini quando o aluno gera um quiz. Sem
// token, qualquer pessoa poderia injetar instruções na IA em nome de um
// professor e para o aluno que quisesse. Agora exige sessão de professor,
// confirma que a turma é dele e que o aluno está matriculado nela.
require_once 'auth.php';

$professor = exigirProfessor($conn);

$idAluno     = intval($_POST['id_aluno'] ?? 0);
$idTurma     = intval($_POST['id_turma'] ?? 0);
$materia     = trim($_POST['materia']      ?? '');
$dificuldade = strtoupper(trim($_POST['dificuldade'] ?? ''));
$instrucao   = trim($_POST['instrucao']    ?? '');
$promptFinal = trim($_POST['prompt_final'] ?? '');

if ($materia === '' || $instrucao === '' || $promptFinal === '') {
    responderErro("Preencha a matéria e a orientação para o aluno.", 422);
}

// Lista fechada: a dificuldade vai para dentro do prompt da IA, então não pode
// ser texto livre vindo do cliente.
if (!in_array($dificuldade, ['FACIL', 'MEDIO', 'DIFICIL'], true)) {
    responderErro("Dificuldade inválida.", 422);
}

// Limites de tamanho: o texto é concatenado no prompt da Gemini e o excesso
// custa tokens sem trazer informação útil.
if (mb_strlen($instrucao) > 1000 || mb_strlen($promptFinal) > 4000 || mb_strlen($materia) > 100) {
    responderErro("Orientação muito longa. Resuma o pedido.", 422);
}

exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);
exigirAlunoNaTurma($conn, $idAluno, $idTurma);

$stmt = $conn->prepare(
    "INSERT INTO prompt_professor (id_professor, id_aluno, id_turma, materia, dificuldade, instrucao, prompt_final)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "iiissss",
    $professor['id_usuario'], $idAluno, $idTurma, $materia, $dificuldade, $instrucao, $promptFinal
);

try {
    $stmt->execute();
    $idPrompt = $stmt->insert_id;
} catch (mysqli_sql_exception $e) {
    error_log("Falha ao salvar prompt do professor: " . $e->getMessage());
    responderErro("Não foi possível salvar a orientação. Tente novamente.", 500);
}
$stmt->close();
$conn->close();

responderJson(["status" => "sucesso", "id_prompt" => intval($idPrompt)]);
