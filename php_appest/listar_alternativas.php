<?php
// Lista as alternativas de uma questão, incluindo qual é a correta.
//
// Duas correções: a consulta era concatenada ("WHERE id_questao=$id_questao")
// e o endpoint era aberto. Como a resposta traz eh_correta, qualquer aluno que
// chamasse isto recebia o gabarito da questão.
//
// Por isso é restrito a professor. Se algum dia a tela do aluno precisar das
// alternativas, ela deve usar um endpoint separado que NÃO devolva eh_correta
// — e não afrouxar a permissão deste aqui.

require_once 'auth.php';

exigirProfessor($conn);

$idQuestao = inteiroDoPost('id_questao');

if ($idQuestao === null) {
    responderErro("Questão inválida.", 422);
}

$stmt = $conn->prepare(
    "SELECT id_alternativa, texto, eh_correta
     FROM alternativa
     WHERE id_questao = ?
     ORDER BY id_alternativa ASC"
);
$stmt->bind_param("i", $idQuestao);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_alternativa'] = intval($row['id_alternativa']);
    $row['eh_correta']     = intval($row['eh_correta']) === 1;
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
