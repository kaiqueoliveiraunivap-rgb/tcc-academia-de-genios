<?php
include 'conexao.php';

if (isset($_POST['nome_turma']) && isset($_POST['ano_letivo']) && isset($_POST['id_professor'])) {
    $nome_turma   = $_POST['nome_turma'];
    $ano_letivo   = $_POST['ano_letivo'];
    $id_professor = intval($_POST['id_professor']);

    $sql = "INSERT INTO turma (nome_turma, ano_letivo, id_professor) VALUES (?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $nome_turma, $ano_letivo, $id_professor);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "id_turma" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>