<?php
// Lista os alunos matriculados numa turma do professor autenticado.
//
// A consulta era concatenada ("WHERE m.id_turma = $id_turma") e o endpoint era
// aberto: como a resposta traz nome e e-mail dos alunos, dava para varrer a
// lista de contatos da escola inteira trocando o número da turma.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$idTurma = inteiroDoPost('id_turma');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}

exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);

$stmt = $conn->prepare(
    "SELECT m.id_matricula, u.id_usuario, u.nome, u.email
     FROM matricula m
     JOIN usuario u ON m.id_aluno = u.id_usuario
     WHERE m.id_turma = ?
     ORDER BY u.nome ASC"
);
$stmt->bind_param("i", $idTurma);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_matricula'] = intval($row['id_matricula']);
    $row['id_usuario']   = intval($row['id_usuario']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
