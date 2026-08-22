<?php
include 'conexao.php';

if (isset($_POST['enunciado']) && isset($_POST['dificuldade']) && isset($_POST['id_materia'])) {
    $enunciado   = $_POST['enunciado'];
    $dificuldade = $_POST['dificuldade'];
    $id_materia  = intval($_POST['id_materia']);

    $sql = "INSERT INTO questao (enunciado, difficulty, id_materia) VALUES (?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $enunciado, $dificuldade, $id_materia);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "id_questao" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>