<?php
require_once __DIR__ . '/auth.php';
$usuario=exigirUsuarioLogado($conn);
$idFrente=filter_var($_POST['id_frente'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$idFrente) responderErro('Frente inválida.',422);
try {
    // A tela usa apenas identificação e progresso; não transfira metadados do
    // livro nem o texto do capítulo a cada abertura da frente.
    $stmt = $conn->prepare('SELECT c.id_capitulo,c.titulo,c.ordem FROM capitulo_livro c JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE l.ativo=1 AND c.revisado=1 AND c.id_frente=? ORDER BY c.ordem');
    $stmt->bind_param('i',$idFrente);
    $stmt->execute();
    $capitulos=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $s=$conn->prepare('SELECT e.id_capitulo,COUNT(*) AS tentativas,MAX(100.0*q.acertos/NULLIF(q.total,0)) AS melhor_percentual
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo
        WHERE q.id_aluno=? AND c.id_frente=? GROUP BY e.id_capitulo');
    $s->bind_param('ii',$usuario['id_usuario'],$idFrente); $s->execute(); $progresso=[];
    foreach ($s->get_result() as $r) $progresso[(int)$r['id_capitulo']]=$r;
    $s->close(); $concluidos=0;
    foreach($capitulos as &$c){
        $c['id_capitulo']=(int)$c['id_capitulo']; $c['ordem']=(int)$c['ordem'];
        $p=$progresso[$c['id_capitulo']] ?? null;
        $c['concluido']=$p!==null;
        $c['tentativas']=(int)($p['tentativas'] ?? 0);
        $c['melhor_percentual']=$p!==null ? (float)$p['melhor_percentual'] : null;
        if ($c['concluido']) $concluidos++;
    } unset($c);
    responderJson(['status'=>'sucesso','capitulos'=>$capitulos,'concluidos'=>$concluidos,'total'=>count($capitulos)]);
} catch (Throwable $e) {
    error_log('Biblioteca: ' . $e->getMessage());
    responderErro('Biblioteca indisponível. Verifique a instalação no servidor.', 503);
}
