<?php
// Apaga uma questão e suas alternativas.
//
// Rodava sem login: qualquer requisição apagava qualquer questão do banco.

require_once 'auth.php';

exigirProfessor($conn);

$idQuestao = inteiroDoPost('id_questao');

if ($idQuestao === null) {
    responderErro("Questão inválida.", 422);
}

// alternativa tem FK para questao: apagar a questão sozinha falharia. As duas
// remoções vão na mesma transação para não deixar alternativa órfã.
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM alternativa WHERE id_questao = ?");
    $stmt->bind_param("i", $idQuestao);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM questao WHERE id_questao = ?");
    $stmt->bind_param("i", $idQuestao);
    $stmt->execute();
    $apagou = $stmt->affected_rows === 1;
    $stmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("deletar_questao: falha ao apagar questão $idQuestao: " . $e->getMessage());
    responderErro("Não foi possível apagar a questão.", 500);
}

if (!$apagou) {
    responderErro("Questão não encontrada.", 404);
}

responderJson(["status" => "deletado"]);
