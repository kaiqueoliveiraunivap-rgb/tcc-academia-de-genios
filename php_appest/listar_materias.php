<?php
// Lista as matérias do catálogo.
//
// O arquivo estava com o corpo errado: apesar do nome, ele fazia um INSERT em
// materia (cópia de inserir_materia.php). Quem chamasse "listar" criava uma
// matéria nova. Agora faz o que o nome diz.
//
// Exige login, mas de qualquer perfil: o aluno precisa da lista para escolher
// a matéria do quiz.

require_once 'auth.php';

exigirUsuarioLogado($conn);

$result = $conn->query("SELECT id_materia, nome FROM materia ORDER BY nome ASC");

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_materia'] = intval($row['id_materia']);
    $dados[] = $row;
}
$result->free();

responderJson($dados);
