<?php
include 'conexao.php';

if (isset($_POST['id_aluno']) && isset($_POST['materia']) && isset($_POST['acertos']) && isset($_POST['total_questoes'])) {
    $id_aluno       = intval($_POST['id_aluno']);
    $materia        = $_POST['materia'];
    $acertos        = intval($_POST['acertos']);
    $total_questoes = intval($_POST['total_questoes']);

    $stmt = $conn->prepare("INSERT INTO historico_quiz (id_aluno, materia, acertos, total_questoes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $id_aluno, $materia, $acertos, $total_questoes);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "id_historico" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>