<?php
// Ranking mensal de uma turma.
//
// A consulta já era preparada, mas o endpoint era aberto: sem login, dava para
// varrer o ranking de todas as turmas da escola trocando o id_turma e coletar
// os nomes dos alunos.
//
// Agora exige login e vínculo com a turma: o aluno vê o ranking da turma em
// que está matriculado, o professor vê o das turmas dele.

require_once 'auth.php';

$usuario = exigirUsuarioLogado($conn);

$idTurma       = inteiroDoPost('id_turma');
$mesReferencia = trim($_POST['mes_referencia'] ?? '');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesReferencia)) {
    responderErro("Mês de referência inválido.", 422);
}

// Uma consulta resolve os dois vínculos possíveis: dono da turma (professor)
// ou matriculado nela (aluno).
$stmt = $conn->prepare(
    "SELECT 1 FROM turma t
     LEFT JOIN matricula m ON m.id_turma = t.id_turma AND m.id_aluno = ?
     WHERE t.id_turma = ? AND (t.id_professor = ? OR m.id_matricula IS NOT NULL)
     LIMIT 1"
);
$stmt->bind_param("iii", $usuario['id_usuario'], $idTurma, $usuario['id_usuario']);
$stmt->execute();
$temVinculo = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$temVinculo) {
    responderErro("Turma não encontrada para este usuário.", 403);
}

$stmt = $conn->prepare(
    "SELECT u.nome, r.pontos, r.mes_referencia
     FROM ranking r
     JOIN usuario u ON r.id_aluno = u.id_usuario
     WHERE r.id_turma = ? AND r.mes_referencia = ?
     ORDER BY r.pontos DESC, u.nome ASC"
);
$stmt->bind_param("is", $idTurma, $mesReferencia);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['pontos'] = intval($row['pontos']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
