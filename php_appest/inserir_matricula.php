<?php
include 'conexao.php';

if (isset($_POST['id_turma']) && isset($_POST['id_aluno'])) {
    $id_turma = intval($_POST['id_turma']);
    $id_aluno = intval($_POST['id_aluno']);

    $stmt = $conn->prepare("INSERT INTO matricula (id_turma, id_aluno) VALUES (?, ?)");
    $stmt->bind_param("ii", $id_turma, $id_aluno);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "id_matricula" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetros ausentes"]);
}
$conn->close();
?>