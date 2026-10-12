<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Histórico exclusivo do aluno.',403);
$antes=inteiroDoPost('antes_id');
if (isset($_POST['antes_id']) && $antes===null) responderErro('Página inválida.',422);
try {
    // Cursor por ID: custo estável mesmo com muitas tentativas, sem OFFSET nem JSON do estudo.
    $sql="SELECT q.id_quiz,q.acertos,q.total,q.criado_em,c.titulo AS capitulo,
        l.materia,l.titulo AS livro,f.titulo AS frente
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo
        JOIN livro_didatico l ON l.id_livro=c.id_livro
        LEFT JOIN frente_livro f ON f.id_frente=c.id_frente
        WHERE q.id_aluno=?";
    if ($antes!==null) $sql.=' AND q.id_quiz<?';
    $s=$conn->prepare($sql.' ORDER BY q.id_quiz DESC LIMIT 21');
    if ($antes!==null) $s->bind_param('ii',$aluno['id_usuario'],$antes);
    else $s->bind_param('i',$aluno['id_usuario']);
    $s->execute(); $itens=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $mais=count($itens)>20;
    if ($mais) array_pop($itens);
    foreach ($itens as &$item) foreach (['id_quiz','acertos','total'] as $campo) $item[$campo]=(int)$item[$campo];
    unset($item);
    responderJson(['status'=>'sucesso','historico'=>$itens,'proximo_cursor'=>$mais ? end($itens)['id_quiz'] : null]);
} catch (Throwable $e) {
    error_log('Histórico de estudos: '.$e->getMessage());
    responderErro('Não foi possível carregar seu histórico. Tente novamente.',503);
}
