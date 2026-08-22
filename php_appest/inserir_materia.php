<?php
include 'conexao.php';

$nome = $_POST['nome'];

$sql = "INSERT INTO materia (nome) VALUES ('$nome')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "sucesso", "id_materia" => $conn->insert_id]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => $conn->error]);
}

$conn->close();
?>
