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

$result = $conn->query("SELECT MIN(l.id_livro) AS id_materia, l.materia AS nome,
 SUM(CASE WHEN c.revisado=1 THEN 1 ELSE 0 END) AS capitulos_disponiveis
 FROM livro_didatico l
 LEFT JOIN capitulo_livro c ON c.id_livro=l.id_livro
 WHERE l.ativo=1
 GROUP BY l.materia ORDER BY l.materia ASC");

$dados = [];
while ($row = $result->fetch_assoc()) {
    $row['id_materia'] = intval($row['id_materia']);
    $row['capitulos_disponiveis'] = intval($row['capitulos_disponiveis']);
    $dados[] = $row;
}
$result->free();

responderJson(['status'=>'sucesso','materias'=>$dados]);
