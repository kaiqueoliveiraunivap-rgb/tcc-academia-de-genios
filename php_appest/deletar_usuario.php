<?php
include 'conexao.php';

if (isset($_POST['id_usuario'])) {
    $id_usuario = intval($_POST['id_usuario']);

    $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);

    if ($stmt->execute()) {
        echo json_encode(["status" => "deletado"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "ID inválido"]);
}
$conn->close();
?>