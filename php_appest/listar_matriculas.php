<?php
include 'conexao.php';

$id_turma = $_POST['id_turma'];

$sql = "SELECT m.id_matricula, u.id_usuario, u.nome, u.email
        FROM matricula m
        JOIN usuario u ON m.id_aluno = u.id_usuario
        WHERE m.id_turma = $id_turma";

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
