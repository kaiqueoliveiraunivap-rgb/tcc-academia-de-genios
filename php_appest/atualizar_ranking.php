<?php
include 'conexao.php';

$id_aluno      = $_POST['id_aluno'];
$id_turma      = $_POST['id_turma'];
$pontos        = $_POST['pontos'];
$mes_referencia = $_POST['mes_referencia']; // formato: 'YYYY-MM'

// Verifica se já existe registro para esse aluno/turma/mês
$check = $conn->query("SELECT id_ranking FROM ranking WHERE id_aluno=$id_aluno AND id_turma=$id_turma AND mes_referencia='$mes_referencia'");

if ($check->num_rows > 0) {
    $sql = "UPDATE ranking SET pontos = pontos + $pontos WHERE id_aluno=$id_aluno AND id_turma=$id_turma AND mes_referencia='$mes_referencia'";
} else {
    $sql = "INSERT INTO ranking (id_aluno, id_turma, pontos, mes_referencia) VALUES ($id_aluno, $id_turma, $pontos, '$mes_referencia')";
}

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "sucesso"]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => $conn->error]);
}

$conn->close();
?>
