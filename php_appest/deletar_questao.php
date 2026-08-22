<?php
include 'conexao.php';

if (isset($_POST['id_questao'])) {
    $id_questao = intval($_POST['id_questao']);

    $stmt = $conn->prepare("DELETE FROM questao WHERE id_questao = ?");
    $stmt->bind_param("i", $id_questao);

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