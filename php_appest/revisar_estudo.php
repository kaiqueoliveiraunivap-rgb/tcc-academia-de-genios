<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Revisão exclusiva do aluno.',403);
$id=inteiroDoPost('id_quiz');
if ($id===null) responderErro('Quiz inválido.',422);
try {
    $s=$conn->prepare('SELECT q.id_quiz,q.acertos,q.total,q.criado_em,e.conteudo_json,
        c.id_capitulo,c.id_frente,c.titulo AS capitulo,c.revisado,l.ativo,
        l.materia,l.titulo AS livro,f.titulo AS frente
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo
        JOIN livro_didatico l ON l.id_livro=c.id_livro
        LEFT JOIN frente_livro f ON f.id_frente=c.id_frente
        WHERE q.id_quiz=? AND q.id_aluno=?');
    $s->bind_param('ii',$id,$aluno['id_usuario']); $s->execute(); $quiz=$s->get_result()->fetch_assoc(); $s->close();
    if (!$quiz) responderErro('Quiz não encontrado para este aluno.',404);
    $conteudo=json_decode($quiz['conteudo_json'],true,512,JSON_THROW_ON_ERROR);
    $s=$conn->prepare('SELECT ordem,acertou,alternativa_escolhida FROM resposta_estudo WHERE id_quiz=? ORDER BY ordem');
    $s->bind_param('i',$id); $s->execute(); $respostas=[];
    foreach ($s->get_result() as $r) $respostas[(int)$r['ordem']]=$r;
    $s->close(); $questoes=[];
    foreach ($conteudo['questoes'] as $i=>$q) {
        $r=$respostas[$i] ?? null;
        $questoes[]=['enunciado'=>$q['enunciado'],'alternativas'=>$q['alternativas'],
            'explicacao'=>$q['explicacao'],'acertou'=>$r===null ? null : (bool)$r['acertou'],
            'escolhida'=>isset($r['alternativa_escolhida']) ? (int)$r['alternativa_escolhida'] : null];
    }
    unset($quiz['conteudo_json']);
    $quiz['pode_refazer']=(bool)$quiz['ativo'] && (bool)$quiz['revisado'] && $quiz['id_frente']!==null;
    unset($quiz['ativo'],$quiz['revisado']);
    foreach (['id_quiz','acertos','total','id_capitulo','id_frente'] as $campo) $quiz[$campo]=(int)$quiz[$campo];
    $quiz['questoes']=$questoes;
    responderJson(['status'=>'sucesso','quiz'=>$quiz]);
} catch (Throwable $e) {
    error_log('Revisão de estudo: '.$e->getMessage());
    responderErro('Não foi possível carregar a revisão. Tente novamente.',503);
}
