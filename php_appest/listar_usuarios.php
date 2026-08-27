<?php
// Lista alunos, para o professor escolher quem matricular numa turma.
//
// Antes devolvia a tabela usuario inteira — nome, e-mail e perfil de TODO
// mundo, professores inclusive — para quem chamasse, sem login. Era a lista de
// contatos da escola aberta na rede, e ainda apontava quais contas eram de
// professor, que é o alvo interessante para um ataque de senha.
//
// Agora exige professor e devolve apenas alunos.

require_once 'auth.php';

exigirProfessor($conn);

$busca = trim($_POST['busca'] ?? '');

if ($busca !== '') {
    // LIKE com prefixo/sufixo montados aqui e passados como parâmetro: os
    // curingas entram no valor, não na consulta.
    $termo = '%' . $busca . '%';
    $stmt  = $conn->prepare(
        "SELECT id_usuario, nome, email
         FROM usuario
         WHERE LOWER(tipo_perfil) = 'aluno' AND (nome LIKE ? OR email LIKE ?)
         ORDER BY nome ASC
         LIMIT 100"
    );
    $stmt->bind_param("ss", $termo, $termo);
} else {
    $stmt = $conn->prepare(
        "SELECT id_usuario, nome, email
         FROM usuario
         WHERE LOWER(tipo_perfil) = 'aluno'
         ORDER BY nome ASC
         LIMIT 100"
    );
}

$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_usuario'] = intval($row['id_usuario']);
    $dados[] = $row;
}
$stmt->close();

responderJson($dados);
