<?php
include 'conexao.php';

$sql    = "SELECT t.id_turma, t.nome_turma, t.ano_letivo, u.nome AS nome_professor
           FROM turma t
           JOIN usuario u ON t.id_professor = u.id_usuario";
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
