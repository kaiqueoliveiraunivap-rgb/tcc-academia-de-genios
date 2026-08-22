<?php
// Busca o prompt mais recente que um professor preparou para este aluno e
// ainda não foi usado. Ao ser lido, é marcado como usado (usado=1) para não
// ser aplicado de novo no próximo quiz. Chamado pelo SelecionarMateriaActivity
// antes de gerar as questões com a Gemini.
//
// Antes o id_aluno vinha do app sem token: dava para ler a orientação que o
// professor escreveu sobre outro aluno e, pior, marcá-la como usada — o aluno
// certo nunca a receberia. Agora o aluno é sempre o dono da sessão.
require_once 'auth.php';

$usuario = exigirUsuarioLogado($conn);
$idAluno = $usuario['id_usuario'];

$stmt = $conn->prepare(
    "SELECT p.id_prompt, p.materia, p.dificuldade, p.instrucao, p.prompt_final, u.nome AS nome_professor
     FROM prompt_professor p
     JOIN usuario u ON p.id_professor = u.id_usuario
     WHERE p.id_aluno = ? AND p.usado = 0
     ORDER BY p.data_criacao DESC
     LIMIT 1"
);
$stmt->bind_param("i", $idAluno);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->num_rows === 1 ? $result->fetch_assoc() : null;
$stmt->close();

if ($row === null) {
    $conn->close();
    responderJson(["status" => "vazio"]);
}

$row['id_prompt'] = intval($row['id_prompt']);

// A condição "usado = 0" é repetida no UPDATE: se o mesmo aluno abrir dois
// quizzes ao mesmo tempo, apenas uma das chamadas consome a orientação.
$upd = $conn->prepare("UPDATE prompt_professor SET usado = 1 WHERE id_prompt = ? AND usado = 0");
$upd->bind_param("i", $row['id_prompt']);
$upd->execute();
$consumiu = $upd->affected_rows === 1;
$upd->close();
$conn->close();

responderJson($consumiu ? ["status" => "sucesso", "prompt" => $row] : ["status" => "vazio"]);
