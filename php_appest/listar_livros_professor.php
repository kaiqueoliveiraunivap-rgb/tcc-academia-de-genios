<?php
require_once __DIR__.'/auth.php';
exigirProfessor($conn);
$materia=is_string($_POST['materia'] ?? null) ? trim($_POST['materia']) : '';
if ($materia==='' || mb_strlen($materia)>100) responderErro('Matéria inválida.',422);
$s=$conn->prepare('SELECT l.id_livro,l.titulo,COUNT(c.id_capitulo) AS capitulos FROM livro_didatico l JOIN capitulo_livro c ON c.id_livro=l.id_livro AND c.revisado=1 WHERE l.ativo=1 AND l.materia=? GROUP BY l.id_livro,l.titulo ORDER BY l.titulo');
$s->bind_param('s',$materia); $s->execute();
responderJson(['status'=>'sucesso','livros'=>$s->get_result()->fetch_all(MYSQLI_ASSOC)]);
