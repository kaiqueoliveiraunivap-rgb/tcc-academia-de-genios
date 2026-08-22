<?php
include 'conexao.php';

if (isset($_POST['id_matricula'])) {
    $id_matricula = intval($_POST['id_matricula']);

    $stmt = $conn->prepare("DELETE FROM matricula WHERE id_matricula = ?");
    $stmt->bind_param("i", $id_matricula);

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