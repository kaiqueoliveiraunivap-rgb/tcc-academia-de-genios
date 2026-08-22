<?php
// Turmas do professor autenticado.
//
// Antes este endpoint aceitava um id_professor vindo do app, sem token: bastava
// trocar o número na requisição para ler a lista de turmas de qualquer outro
// professor. Agora o id vem da sessão e o parâmetro do cliente é ignorado.
require_once 'auth.php';

$professor = exigirProfessor($conn);

$sql = "SELECT t.id_turma, t.nome_turma, t.ano_letivo,
               COUNT(m.id_matricula) AS total_alunos
        FROM turma t
        LEFT JOIN matricula m ON m.id_turma = t.id_turma
        WHERE t.id_professor = ?
        GROUP BY t.id_turma, t.nome_turma, t.ano_letivo
        ORDER BY t.ano_letivo DESC, t.nome_turma ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $professor['id_usuario']);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_turma']     = intval($row['id_turma']);
    $row['total_alunos'] = intval($row['total_alunos']);
    $dados[] = $row;
}
$stmt->close();
$conn->close();

responderJson($dados);
