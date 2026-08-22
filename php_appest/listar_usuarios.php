<?php
include 'conexao.php';

$sql    = "SELECT id_usuario, nome, email, tipo_perfil FROM usuario";
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
