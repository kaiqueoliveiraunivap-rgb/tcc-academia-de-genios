<?php
include 'conexao.php';

$id_questao = $_POST['id_questao'];

$sql = "SELECT id_alternativa, texto, eh_correta FROM alternativa WHERE id_questao=$id_questao";

$result = $conn->query($sql);
$dados  = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
}

echo json_encode($dados);
$conn->close();
?>
