<?php
include 'conexao.php';

if (isset($_POST['nome'])) {
    $nome = $_POST['nome'];

    $stmt = $conn->prepare("INSERT INTO materia (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "id_materia" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Nome não enviado"]);
}
$conn->close();
?>