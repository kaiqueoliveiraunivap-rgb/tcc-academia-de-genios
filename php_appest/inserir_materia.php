<?php
// Cadastra uma matéria no catálogo.
//
// A consulta era montada por concatenação ("INSERT ... VALUES ('$nome')"), o
// que levava SQL direto para o banco, e rodava sem login.

require_once 'auth.php';

exigirProfessor($conn);

$nome = trim($_POST['nome'] ?? '');

if ($nome === '' || mb_strlen($nome) > 100) {
    responderErro("Informe um nome de matéria com até 100 caracteres.", 422);
}

$stmt = $conn->prepare("INSERT INTO materia (nome) VALUES (?)");
$stmt->bind_param("s", $nome);

if (!$stmt->execute()) {
    $stmt->close();
    error_log("inserir_materia: falha ao inserir matéria");
    responderErro("Não foi possível cadastrar a matéria.", 500);
}

$idMateria = $stmt->insert_id;
$stmt->close();

responderJson(["status" => "sucesso", "id_materia" => intval($idMateria)], 201);
