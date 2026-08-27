<?php
// Soma os pontos de um quiz ao ranking do mês.
//
// Chamado pelo app ao final do quiz (MainActivity.finalizarQuiz).
//
// Quem pontua é sempre o dono do token, nunca o id_aluno que vier no corpo da
// requisição: antes desta correção bastava trocar o número enviado para somar
// pontos na conta de outro aluno.

require_once 'auth.php';

// Limite de pontos por chamada. O servidor não tem como conferir quantas
// questões o quiz realmente tinha (as questões são geradas pela Gemini no
// próprio aparelho), então este teto é o que impede um envio manual de somar
// um valor absurdo de uma vez.
const RANKING_PONTOS_MAXIMO = 100;

$aluno    = exigirUsuarioLogado($conn);
$idAluno  = $aluno['id_usuario'];

$idTurma = inteiroDoPost('id_turma');
$pontos  = inteiroDoPost('pontos', 0, RANKING_PONTOS_MAXIMO);
$mesReferencia = trim($_POST['mes_referencia'] ?? '');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}
if ($pontos === null) {
    responderErro("Pontuação inválida.", 422);
}
// Formato 'YYYY-MM'. Validar aqui evita gravar lixo numa coluna que é usada
// para agrupar o ranking do mês.
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesReferencia)) {
    responderErro("Mês de referência inválido.", 422);
}

// O aluno só pontua em turma na qual está matriculado.
$stmt = $conn->prepare(
    "SELECT 1 FROM matricula WHERE id_aluno = ? AND id_turma = ? LIMIT 1"
);
$stmt->bind_param("ii", $idAluno, $idTurma);
$stmt->execute();
$matriculado = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$matriculado) {
    responderErro("Aluno não está matriculado nesta turma.", 403);
}

// Upsert atômico: a UNIQUE KEY (id_aluno, id_turma, mes_referencia) resolve o
// conflito no próprio banco. O SELECT-depois-INSERT anterior podia duplicar ou
// perder pontos quando dois quizzes terminavam ao mesmo tempo.
$stmt = $conn->prepare(
    "INSERT INTO ranking (id_aluno, id_turma, pontos, mes_referencia)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE pontos = pontos + VALUES(pontos)"
);
$stmt->bind_param("iiis", $idAluno, $idTurma, $pontos, $mesReferencia);

if (!$stmt->execute()) {
    $stmt->close();
    // A mensagem do MySQL fica no log do servidor, não na resposta: ela revela
    // nomes de tabelas e colunas para quem estiver sondando a API.
    error_log("atualizar_ranking: falha ao gravar pontos do aluno $idAluno");
    responderErro("Não foi possível registrar a pontuação.", 500);
}
$stmt->close();

responderJson(["status" => "sucesso"]);
