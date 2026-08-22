<?php
include 'conexao.php';

if (isset($_POST['id_aluno'])) {
    $id_aluno = intval($_POST['id_aluno']);

    // Busca os quizzes do mais recente para o mais antigo
    $stmt = $conn->prepare("SELECT materia, acertos, total_questoes, DATE_FORMAT(data_realizacao, '%d/%m') as data_formatada FROM historico_quiz WHERE id_aluno = ? ORDER BY id_historico ASC");
    $stmt->bind_param("i", $id_aluno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }

    echo json_encode($dados);
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "ID do aluno nao fornecido"]);
}
$conn->close();
?>