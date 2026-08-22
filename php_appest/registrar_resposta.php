<?php
include 'conexao.php';

if (isset($_POST['id_aluno']) && isset($_POST['enunciado']) && isset($_POST['acertou'])) {
    $id_aluno = intval($_POST['id_aluno']);
    $enunciado = $_POST['enunciado'];
    $acertou = intval($_POST['acertou']); // 1 para acertou, 0 para errou

    // Insere sem precisar de uma ID de questão física do banco
    $sql = "INSERT INTO historico (id_aluno, id_questao, id_alternativa, acertou, enunciado_gemini)
            VALUES (?, NULL, NULL, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $id_aluno, $acertou, $enunciado);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "acertou" => (bool)$acertou]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>