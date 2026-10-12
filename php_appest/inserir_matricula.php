<?php
// Matricula um aluno numa turma do professor autenticado.
//
// Rodava sem login e sem checar dono: dava para matricular qualquer usuário em
// qualquer turma da escola.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$idTurma = inteiroDoPost('id_turma');
$idAluno = inteiroDoPost('id_aluno');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}
if ($idAluno === null) {
    responderErro("Aluno inválido.", 422);
}

// A turma precisa ser deste professor.
exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);

// Só matricula quem é aluno: sem esta checagem dava para matricular um
// professor como se fosse estudante da própria turma.
$stmt = $conn->prepare(
    "SELECT 1 FROM usuario WHERE id_usuario = ? AND LOWER(tipo_perfil) = 'aluno' AND excluido_em IS NULL LIMIT 1"
);
$stmt->bind_param("i", $idAluno);
$stmt->execute();
$ehAluno = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$ehAluno) {
    responderErro("Aluno não encontrado.", 404);
}

$stmt = $conn->prepare("INSERT INTO matricula (id_turma, id_aluno) VALUES (?, ?)");
$stmt->bind_param("ii", $idTurma, $idAluno);

try {
    $stmt->execute();
    $idMatricula = $stmt->insert_id;
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    // 1062 = a UNIQUE KEY (id_aluno, id_turma) já tem este par.
    if ($e->getCode() === 1062) {
        responderErro("Este aluno já está matriculado na turma.", 409);
    }
    error_log("inserir_matricula: " . $e->getMessage());
    responderErro("Não foi possível matricular o aluno.", 500);
}
$stmt->close();

responderJson(["status" => "sucesso", "id_matricula" => intval($idMatricula)], 201);
