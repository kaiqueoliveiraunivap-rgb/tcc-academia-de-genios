<?php
// Renomeia uma turma do professor autenticado.
//
// Duas correções aqui:
//   1. A consulta era montada por concatenação de string, então nome_turma
//      carregava SQL direto para o banco.
//   2. Não havia login nem checagem de dono: bastava mandar um id_turma para
//      alterar a turma de outro professor — inclusive passar id_professor e
//      transferir a turma para si.
//
// id_professor não é mais aceito: a turma continua com o dono que já tem.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$idTurma   = inteiroDoPost('id_turma');
$nomeTurma = trim($_POST['nome_turma'] ?? '');
$anoLetivo = trim($_POST['ano_letivo'] ?? '');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}
if ($nomeTurma === '' || mb_strlen($nomeTurma) > 80) {
    responderErro("Informe um nome de turma com até 80 caracteres.", 422);
}
if (!preg_match('/^\d{4}$/', $anoLetivo)) {
    responderErro("Ano letivo inválido.", 422);
}

// Confirma no banco que a turma é deste professor.
exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);

$stmt = $conn->prepare(
    "UPDATE turma SET nome_turma = ?, ano_letivo = ? WHERE id_turma = ? AND id_professor = ?"
);
$stmt->bind_param("ssii", $nomeTurma, $anoLetivo, $idTurma, $professor['id_usuario']);

if (!$stmt->execute()) {
    $stmt->close();
    error_log("atualizar_turma: falha ao atualizar turma $idTurma");
    responderErro("Não foi possível atualizar a turma.", 500);
}
$stmt->close();

responderJson(["status" => "atualizado"]);
