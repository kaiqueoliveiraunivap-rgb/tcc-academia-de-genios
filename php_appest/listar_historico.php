<?php
// Histórico de quizzes do aluno autenticado.
//
// O id_aluno vinha do corpo da requisição e não havia login: trocar o número
// devolvia o histórico de desempenho de outro aluno. Agora o histórico é
// sempre o de quem está logado.

require_once 'auth.php';

$aluno = exigirUsuarioLogado($conn);

// Ordem cronológica: o gráfico de evolução do app depende disso.
$stmt = $conn->prepare(
    "SELECT materia, acertos, total_questoes,
            DATE_FORMAT(data_realizacao, '%d/%m') AS data_formatada
     FROM historico_quiz
     WHERE id_aluno = ?
     ORDER BY id_historico ASC"
);
$stmt->bind_param("i", $aluno['id_usuario']);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['acertos']        = intval($row['acertos']);
    $row['total_questoes'] = intval($row['total_questoes']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
