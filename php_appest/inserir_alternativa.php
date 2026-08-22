<?php
include 'conexao.php';

$id_questao = $_POST['id_questao'];
$texto      = $_POST['texto'];
$eh_correta = $_POST['eh_correta']; // 1 = correta, 0 = errada

$sql = "INSERT INTO alternativa (id_questao, texto, eh_correta) VALUES ($id_questao, '$texto', $eh_correta)";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "sucesso", "id_alternativa" => $conn->insert_id]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => $conn->error]);
}

$conn->close();
?>
