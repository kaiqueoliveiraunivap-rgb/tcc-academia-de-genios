<?php
// Edição de uma nota pelo professor.
//
// Duas barreiras, nesta ordem:
//   1) a nota precisa pertencer a uma avaliação de uma turma DESTE professor
//      (validado por consulta ao banco, não por nada vindo do app);
//   2) o valor precisa estar dentro da escala 0..10.
//
// O próprio UPDATE repete a checagem de dono no WHERE, para que a permissão
// não dependa de a validação anterior ter sido executada.

require_once 'auth.php';
require_once 'dashboard_util.php';

$professor   = exigirProfessor($conn);
$idProfessor = $professor['id_usuario'];

$idNota    = intval($_POST['id_nota'] ?? 0);
$valorBruto = trim($_POST['valor'] ?? '');

if ($idNota <= 0) {
    responderErro("Nota não informada.", 400);
}
if ($valorBruto === '') {
    responderErro("Informe a nota.", 422);
}

// Aceita vírgula como separador decimal (o teclado do celular usa vírgula).
$valorNormalizado = str_replace(',', '.', $valorBruto);

if (!is_numeric($valorNormalizado)) {
    responderErro("A nota deve ser um número.", 422);
}

$valor = round(floatval($valorNormalizado), 2);

if ($valor < NOTA_MINIMA || $valor > NOTA_MAXIMA) {
    responderErro("A nota deve estar entre " . NOTA_MINIMA . " e " . NOTA_MAXIMA . ".", 422);
}

// ── 1) A nota é de uma turma deste professor? ───────────────────────────────
$stmt = $conn->prepare(
    "SELECT n.id_nota, n.valor, a.id_turma, a.titulo, u.nome AS nome_aluno
     FROM nota n
     JOIN avaliacao a ON a.id_avaliacao = n.id_avaliacao
     JOIN turma t     ON t.id_turma = a.id_turma
     JOIN usuario u   ON u.id_usuario = n.id_aluno
     WHERE n.id_nota = ? AND t.id_professor = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $idNota, $idProfessor);
$stmt->execute();
$result = $stmt->get_result();
$registro = $result->num_rows === 1 ? $result->fetch_assoc() : null;
$stmt->close();

if ($registro === null) {
    responderErro("Você não tem permissão para editar esta nota.", 403);
}

// ── 2) Atualiza (a condição de dono é repetida aqui de propósito) ───────────
$stmt = $conn->prepare(
    "UPDATE nota n
     JOIN avaliacao a ON a.id_avaliacao = n.id_avaliacao
     JOIN turma t     ON t.id_turma = a.id_turma
     SET n.valor = ?, n.atualizado_por = ?
     WHERE n.id_nota = ? AND t.id_professor = ?"
);
$stmt->bind_param("diii", $valor, $idProfessor, $idNota, $idProfessor);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if (!$ok) {
    responderErro("Não foi possível salvar a nota.", 500);
}

responderJson([
    "status"     => "sucesso",
    "mensagem"   => "Nota de " . $registro['nome_aluno'] . " atualizada.",
    "id_nota"    => $idNota,
    "id_turma"   => intval($registro['id_turma']),
    "valor"      => arredondar($valor)
]);
