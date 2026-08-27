<?php
// Grava o resultado de um quiz no histórico do aluno.
//
// O id_aluno vinha do corpo da requisição e não havia login, então dava para
// gravar histórico falso na conta de qualquer aluno. Agora o dono do histórico
// é sempre o dono do token.

require_once 'auth.php';

// Mesmo teto usado em atualizar_ranking.php: o servidor não conhece o quiz
// (as questões são geradas no aparelho), então limita o que aceita.
const HISTORICO_TOTAL_MAXIMO = 100;

$aluno = exigirUsuarioLogado($conn);

$materia       = trim($_POST['materia'] ?? '');
$acertos       = inteiroDoPost('acertos', 0, HISTORICO_TOTAL_MAXIMO);
$totalQuestoes = inteiroDoPost('total_questoes', 1, HISTORICO_TOTAL_MAXIMO);

if ($materia === '' || mb_strlen($materia) > 100) {
    responderErro("Informe a matéria do quiz.", 422);
}
if ($acertos === null) {
    responderErro("Número de acertos inválido.", 422);
}
if ($totalQuestoes === null) {
    responderErro("Total de questões inválido.", 422);
}
if ($acertos > $totalQuestoes) {
    responderErro("Acertos não pode ser maior que o total de questões.", 422);
}

$stmt = $conn->prepare(
    "INSERT INTO historico_quiz (id_aluno, materia, acertos, total_questoes)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("isii", $aluno['id_usuario'], $materia, $acertos, $totalQuestoes);

if (!$stmt->execute()) {
    $stmt->close();
    error_log("salvar_historico_quiz: falha ao gravar histórico do aluno " . $aluno['id_usuario']);
    responderErro("Não foi possível salvar o histórico.", 500);
}

$idHistorico = $stmt->insert_id;
$stmt->close();

responderJson(["status" => "sucesso", "id_historico" => intval($idHistorico)], 201);
