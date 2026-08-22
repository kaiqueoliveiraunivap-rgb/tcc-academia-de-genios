<?php
include 'conexao.php';

$id_turma     = $_POST['id_turma'];
$nome_turma   = $_POST['nome_turma'];
$ano_letivo   = $_POST['ano_letivo'];
$id_professor = $_POST['id_professor'];

$sql = "UPDATE turma SET nome_turma='$nome_turma', ano_letivo='$ano_letivo', id_professor=$id_professor WHERE id_turma=$id_turma";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "atualizado"]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => $conn->error]);
}

$conn->close();
?>
