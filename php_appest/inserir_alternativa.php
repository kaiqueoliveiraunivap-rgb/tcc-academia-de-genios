<?php
// Cadastra uma alternativa de uma questão.
//
// O INSERT era concatenado ("VALUES ($id_questao, '$texto', $eh_correta)"),
// então texto levava SQL direto para o banco, e não havia login.

require_once 'auth.php';

exigirProfessor($conn);

$idQuestao = inteiroDoPost('id_questao');
$texto     = trim($_POST['texto'] ?? '');
$ehCorreta = inteiroDoPost('eh_correta', 0, 1);

if ($idQuestao === null) {
    responderErro("Questão inválida.", 422);
}
if ($texto === '' || mb_strlen($texto) > 255) {
    responderErro("Informe um texto de alternativa com até 255 caracteres.", 422);
}
if ($ehCorreta === null) {
    responderErro("O campo eh_correta deve ser 0 ou 1.", 422);
}

$stmt = $conn->prepare(
    "INSERT INTO alternativa (id_questao, texto, eh_correta) VALUES (?, ?, ?)"
);
$stmt->bind_param("isi", $idQuestao, $texto, $ehCorreta);

try {
    $stmt->execute();
    $idAlternativa = $stmt->insert_id;
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    if ($e->getCode() === 1452) {
        responderErro("Questão não encontrada.", 422);
    }
    error_log("inserir_alternativa: " . $e->getMessage());
    responderErro("Não foi possível cadastrar a alternativa.", 500);
}
$stmt->close();

responderJson(["status" => "sucesso", "id_alternativa" => intval($idAlternativa)], 201);
