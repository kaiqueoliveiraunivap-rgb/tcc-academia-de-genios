<?php
// Lista as questões de uma matéria.
//
// A consulta já era preparada; o que faltava era login. Sem isso o banco de
// questões da escola ficava aberto para qualquer um baixar.

require_once 'auth.php';

exigirProfessor($conn);

$idMateria = inteiroDoPost('id_materia');

if ($idMateria === null) {
    responderErro("Matéria inválida.", 422);
}

$stmt = $conn->prepare(
    "SELECT q.id_questao, q.enunciado, q.dificuldade, m.nome AS materia
     FROM questao q
     JOIN materia m ON q.id_materia = m.id_materia
     WHERE q.id_materia = ?
     ORDER BY q.id_questao ASC"
);
$stmt->bind_param("i", $idMateria);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_questao'] = intval($row['id_questao']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
