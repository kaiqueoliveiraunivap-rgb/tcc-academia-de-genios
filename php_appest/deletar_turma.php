<?php
include 'conexao.php';

if (isset($_POST['id_turma'])) {
    $id_turma = intval($_POST['id_turma']);

    $stmt = $conn->prepare("DELETE FROM turma WHERE id_turma = ?");
    $stmt->bind_param("i", $id_turma);

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