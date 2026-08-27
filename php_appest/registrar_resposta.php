<?php
// Registra a resposta do aluno a uma questão gerada pela Gemini.
//
// As questões do quiz não existem na tabela questao (vêm da IA no aparelho),
// por isso id_questao e id_alternativa ficam nulos e o enunciado é gravado em
// enunciado_gemini.
//
// O id_aluno vinha do cliente e não havia login: dava para encher o histórico
// de outro aluno com respostas inventadas. Agora é sempre o dono do token.

require_once 'auth.php';

$aluno = exigirUsuarioLogado($conn);

$enunciado = trim($_POST['enunciado'] ?? '');
$acertou   = inteiroDoPost('acertou', 0, 1);

if ($enunciado === '') {
    responderErro("Enunciado não informado.", 422);
}
if ($acertou === null) {
    responderErro("O campo acertou deve ser 0 ou 1.", 422);
}

$stmt = $conn->prepare(
    "INSERT INTO historico (id_aluno, id_questao, id_alternativa, acertou, enunciado_gemini)
     VALUES (?, NULL, NULL, ?, ?)"
);
$stmt->bind_param("iis", $aluno['id_usuario'], $acertou, $enunciado);

if (!$stmt->execute()) {
    $stmt->close();
    error_log("registrar_resposta: falha ao gravar resposta do aluno " . $aluno['id_usuario']);
    responderErro("Não foi possível registrar a resposta.", 500);
}
$stmt->close();

responderJson(["status" => "sucesso", "acertou" => $acertou === 1]);
