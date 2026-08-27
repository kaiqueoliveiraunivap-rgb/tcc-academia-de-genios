<?php
// Remove a matrícula de um aluno numa turma do professor autenticado.
//
// Rodava sem login: qualquer requisição com um id_matricula desmatriculava o
// aluno correspondente, em qualquer turma.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$idMatricula = inteiroDoPost('id_matricula');

if ($idMatricula === null) {
    responderErro("Matrícula inválida.", 422);
}

// O DELETE já filtra pelo professor pelo JOIN com turma, então uma matrícula
// de outro professor simplesmente não é encontrada.
$stmt = $conn->prepare(
    "DELETE m FROM matricula m
     JOIN turma t ON t.id_turma = m.id_turma
     WHERE m.id_matricula = ? AND t.id_professor = ?"
);
$stmt->bind_param("ii", $idMatricula, $professor['id_usuario']);

try {
    $stmt->execute();
    $apagou = $stmt->affected_rows === 1;
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    error_log("deletar_matricula: " . $e->getMessage());
    responderErro("Não foi possível remover a matrícula.", 500);
}
$stmt->close();

if (!$apagou) {
    responderErro("Matrícula não encontrada para este professor.", 403);
}

responderJson(["status" => "deletado"]);
