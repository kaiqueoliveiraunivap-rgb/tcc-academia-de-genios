<?php
// Cadastra uma questão numa matéria.
//
// Além da falta de login, o INSERT usava a coluna "difficulty", que não existe
// no schema (a coluna é "dificuldade"). Ou seja, este endpoint nunca chegou a
// gravar nada: falhava em toda chamada.

require_once 'auth.php';

exigirProfessor($conn);

$enunciado   = trim($_POST['enunciado'] ?? '');
$dificuldade = 'MEDIO';
$idMateria   = inteiroDoPost('id_materia');

if ($enunciado === '') {
    responderErro("Informe o enunciado da questão.", 422);
}
if ($idMateria === null) {
    responderErro("Matéria inválida.", 422);
}

$stmt = $conn->prepare(
    "INSERT INTO questao (enunciado, dificuldade, id_materia) VALUES (?, ?, ?)"
);
$stmt->bind_param("ssi", $enunciado, $dificuldade, $idMateria);

try {
    $stmt->execute();
    $idQuestao = $stmt->insert_id;
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    // 1452 = FK violada, ou seja, a matéria informada não existe.
    if ($e->getCode() === 1452) {
        responderErro("Matéria não encontrada.", 422);
    }
    error_log("inserir_questao: " . $e->getMessage());
    responderErro("Não foi possível cadastrar a questão.", 500);
}
$stmt->close();

responderJson(["status" => "sucesso", "id_questao" => intval($idQuestao)], 201);
