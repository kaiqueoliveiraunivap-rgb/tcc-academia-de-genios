<?php
// Lista as turmas do professor autenticado.
//
// Antes devolvia TODAS as turmas da escola, com o nome do professor de cada
// uma, para quem chamasse — sem login. Agora o professor vê só as suas.
//
// Para o dashboard, prefira listar_turmas_professor.php, que já traz os
// contadores de aluno. Este endpoint devolve só o essencial.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$stmt = $conn->prepare(
    "SELECT id_turma, nome_turma, ano_letivo
     FROM turma
     WHERE id_professor = ?
     ORDER BY ano_letivo DESC, nome_turma ASC"
);
$stmt->bind_param("i", $professor['id_usuario']);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_turma'] = intval($row['id_turma']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
