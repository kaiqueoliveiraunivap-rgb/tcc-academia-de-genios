<?php
include 'conexao.php';

if (isset($_POST['id_materia'])) {
    $id_materia = intval($_POST['id_materia']);

    $sql = "SELECT q.id_questao, q.enunciado, q.dificuldade, m.nome AS materia
            FROM questao q
            JOIN materia m ON q.id_materia = m.id_materia
            WHERE q.id_materia = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_materia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $row['id_questao'] = intval($row['id_questao']);
        $dados[] = $row;
    }

    echo json_encode($dados);
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Matéria inválida"]);
}
$conn->close();
?>