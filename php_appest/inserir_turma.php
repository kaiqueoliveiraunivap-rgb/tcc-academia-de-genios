<?php
// Cria uma turma para o professor autenticado.
//
// O id_professor deixa de vir do corpo da requisição: a turma é sempre criada
// para o dono do token. Antes, qualquer um (sem login) podia criar turma em
// nome de qualquer professor.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$nomeTurma = trim($_POST['nome_turma'] ?? '');
$anoLetivo = trim($_POST['ano_letivo'] ?? '');

if ($nomeTurma === '' || mb_strlen($nomeTurma) > 80) {
    responderErro("Informe um nome de turma com até 80 caracteres.", 422);
}
if (!preg_match('/^\d{4}$/', $anoLetivo)) {
    responderErro("Ano letivo inválido.", 422);
}

$stmt = $conn->prepare(
    "INSERT INTO turma (nome_turma, ano_letivo, id_professor) VALUES (?, ?, ?)"
);
$stmt->bind_param("ssi", $nomeTurma, $anoLetivo, $professor['id_usuario']);

if (!$stmt->execute()) {
    $stmt->close();
    error_log("inserir_turma: falha ao criar turma do professor " . $professor['id_usuario']);
    responderErro("Não foi possível criar a turma.", 500);
}

$idTurma = $stmt->insert_id;
$stmt->close();

responderJson(["status" => "sucesso", "id_turma" => intval($idTurma)], 201);
