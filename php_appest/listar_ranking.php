<?php
include 'conexao.php';

if (isset($_POST['id_turma']) && isset($_POST['mes_referencia'])) {
    $id_turma = intval($_POST['id_turma']);
    $mes_referencia = $_POST['mes_referencia'];

    $sql = "SELECT u.nome, r.pontos, r.mes_referencia
            FROM ranking r
            JOIN usuario u ON r.id_aluno = u.id_usuario
            WHERE r.id_turma = ? AND r.mes_referencia = ?
            ORDER BY r.pontos DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id_turma, $mes_referencia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $row['pontos'] = intval($row['pontos']); // Força tipo Int no JSON
        $dados[] = $row;
    }

    echo json_encode($dados);
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetros ausentes"]);
}
$conn->close();
?>