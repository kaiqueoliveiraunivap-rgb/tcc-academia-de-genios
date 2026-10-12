<?php
require_once __DIR__.'/auth.php';
$usuario=exigirUsuarioLogado($conn);
$materia=trim((string)($_POST['materia'] ?? ''));
if ($materia==='' || mb_strlen($materia)>80) responderErro('Matéria inválida.',422);
try {
    // fonte_url é necessário somente ao abrir o estudo, não na lista.
    $s=$conn->prepare("SELECT f.id_frente,f.titulo,f.ordem,l.titulo AS livro,
      COUNT(c.id_capitulo) AS total_capitulos
      FROM frente_livro f
      JOIN livro_didatico l ON l.id_livro=f.id_livro
      JOIN capitulo_livro c ON c.id_frente=f.id_frente AND c.revisado=1
      WHERE l.ativo=1 AND l.materia=?
      GROUP BY f.id_frente,f.titulo,f.ordem,l.titulo,l.fonte_url
      ORDER BY l.titulo,f.ordem");
    $s->bind_param('s',$materia); $s->execute();
    $dados=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    // Uma consulta agregada para todas as frentes; refazer não aumenta capítulos concluídos.
    $s=$conn->prepare('SELECT c.id_frente,COUNT(DISTINCT c.id_capitulo) AS concluidos
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo
        JOIN livro_didatico l ON l.id_livro=c.id_livro
        WHERE q.id_aluno=? AND l.materia=? AND l.ativo=1 AND c.revisado=1 GROUP BY c.id_frente');
    $s->bind_param('is',$usuario['id_usuario'],$materia); $s->execute(); $progresso=[];
    foreach ($s->get_result() as $r) $progresso[(int)$r['id_frente']]=(int)$r['concluidos'];
    $s->close();
    foreach($dados as &$d){$d['id_frente']=(int)$d['id_frente'];$d['ordem']=(int)$d['ordem'];$d['total_capitulos']=(int)$d['total_capitulos'];$d['concluidos']=$progresso[$d['id_frente']] ?? 0;} unset($d);
    responderJson(['status'=>'sucesso','frentes'=>$dados]);
} catch(Throwable $e){
    error_log('Frentes: '.$e->getMessage());
    responderErro('Não foi possível carregar as frentes desta matéria.',503);
}
