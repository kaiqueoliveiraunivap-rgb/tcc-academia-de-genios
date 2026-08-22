<?php
// Desempenho de cada aluno de uma turma, somando os pontos que ele já
// acumulou na tabela "ranking" (preenchida a cada quiz finalizado).
// Usado pelo gráfico de desempenho do professor (TurmaDesempenhoActivity).
//
// Antes bastava mandar um id_turma qualquer, sem token, para ler o desempenho
// nominal dos alunos de outro professor. Agora exige sessão de professor e
// confirma no banco que a turma é dele.
require_once 'auth.php';

$professor = exigirProfessor($conn);
$idTurma   = intval($_POST['id_turma'] ?? 0);

exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);

$sql = "SELECT u.id_usuario, u.nome,
               COALESCE(SUM(r.pontos), 0) AS pontos_total,
               COUNT(DISTINCT r.mes_referencia) AS meses_participados
        FROM matricula m
        JOIN usuario u ON m.id_aluno = u.id_usuario
        LEFT JOIN ranking r ON r.id_aluno = u.id_usuario AND r.id_turma = m.id_turma
        WHERE m.id_turma = ?
        GROUP BY u.id_usuario, u.nome
        ORDER BY pontos_total DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idTurma);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_usuario']         = intval($row['id_usuario']);
    $row['pontos_total']       = intval($row['pontos_total']);
    $row['meses_participados'] = intval($row['meses_participados']);
    $dados[] = $row;
}
$stmt->close();
$conn->close();

responderJson($dados);
