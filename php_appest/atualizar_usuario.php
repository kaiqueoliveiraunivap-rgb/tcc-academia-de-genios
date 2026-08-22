<?php
include 'conexao.php';

if (isset($_POST['id_usuario']) && isset($_POST['nome']) && isset($_POST['email']) && isset($_POST['tipo_perfil'])) {
    $id_usuario  = intval($_POST['id_usuario']);
    $nome        = $_POST['nome'];
    $email       = $_POST['email'];
    $tipo_perfil = strtolower($_POST['tipo_perfil']);

    $sql = "UPDATE usuario SET nome=?, email=?, tipo_perfil=? WHERE id_usuario=?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $nome, $email, $tipo_perfil, $id_usuario);

    if ($stmt->execute()) {
        echo json_encode(["status" => "atualizado"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>