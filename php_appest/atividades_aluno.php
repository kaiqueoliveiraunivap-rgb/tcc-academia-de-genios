<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Acesso exclusivo de alunos.',403);
$acao=$_POST['acao'] ?? 'listar';
if (!in_array($acao,['listar','abrir'],true)) responderErro('Ação inválida.',422);

if ($acao==='abrir') {
    $id=inteiroDoPost('id_atividade');
    if (!$id) responderErro('Atividade inválida.',422);
    $s=$conn->prepare('SELECT a.id_atividade,a.id_conteudo,a.id_turma,a.titulo,cp.nome,cp.materia,cc.id_capitulo
        FROM atividade_publicada a JOIN turma t ON t.id_turma=a.id_turma
        JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
        JOIN conteudo_pedagogico cp ON cp.id_conteudo=a.id_conteudo AND cp.serie=t.serie AND cp.ativo=1
        JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
        JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
        JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
        WHERE a.id_atividade=? AND a.ativa=1 ORDER BY cc.ordem,c.ordem LIMIT 1');
    $s->bind_param('ii',$aluno['id_usuario'],$id); $s->execute(); $atividade=$s->get_result()->fetch_assoc(); $s->close();
    if (!$atividade) responderErro('Atividade indisponível para sua turma.',403);
    $s=$conn->prepare('INSERT INTO progresso_conteudo(id_aluno,id_conteudo,id_capitulo_atual)
        VALUES (?,?,?) ON DUPLICATE KEY UPDATE ultimo_acesso=NOW()');
    $s->bind_param('iii',$aluno['id_usuario'],$atividade['id_conteudo'],$atividade['id_capitulo']); $s->execute(); $s->close();
    responderJson(['status'=>'sucesso','atividade'=>[
        'id_atividade'=>(int)$atividade['id_atividade'],'id_conteudo'=>(int)$atividade['id_conteudo'],
        'id_turma'=>(int)$atividade['id_turma'],'id_capitulo'=>(int)$atividade['id_capitulo'],
        'titulo'=>$atividade['titulo'],'nome'=>$atividade['nome'],'materia'=>$atividade['materia']
    ]]);
}

$s=$conn->prepare('SELECT a.id_atividade,a.id_conteudo,a.id_turma,a.titulo,a.publicada_em,a.prazo_em,
    cp.nome AS conteudo,cp.materia,cp.bimestre,t.nome_turma,
    COUNT(DISTINCT q.id_quiz) AS tentativas,MAX(100.0*q.acertos/NULLIF(q.total,0)) AS melhor_percentual,
    MAX(p.ultimo_acesso) AS ultimo_acesso
    FROM atividade_publicada a JOIN turma t ON t.id_turma=a.id_turma
    JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
    JOIN conteudo_pedagogico cp ON cp.id_conteudo=a.id_conteudo AND cp.serie=t.serie AND cp.ativo=1
    LEFT JOIN atividade_quiz aq ON aq.id_atividade=a.id_atividade
    LEFT JOIN quiz_estudo q ON q.id_quiz=aq.id_quiz AND q.id_aluno=?
    LEFT JOIN progresso_conteudo p ON p.id_conteudo=cp.id_conteudo AND p.id_aluno=? AND p.ultimo_acesso>=a.publicada_em
    WHERE a.ativa=1 AND EXISTS (SELECT 1 FROM conteudo_capitulo cc
        JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
        JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1
        WHERE cc.id_conteudo=cp.id_conteudo AND l.materia=cp.materia)
    GROUP BY a.id_atividade,a.id_conteudo,a.id_turma,a.titulo,a.publicada_em,a.prazo_em,
        cp.nome,cp.materia,cp.bimestre,t.nome_turma
    ORDER BY a.publicada_em DESC,a.id_atividade DESC LIMIT 100');
$s->bind_param('iii',$aluno['id_usuario'],$aluno['id_usuario'],$aluno['id_usuario']);
$s->execute(); $itens=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
foreach ($itens as &$item) {
    foreach (['id_atividade','id_conteudo','id_turma','tentativas'] as $chave) $item[$chave]=(int)$item[$chave];
    $item['bimestre']=$item['bimestre']===null ? null : (int)$item['bimestre'];
    $item['melhor_percentual']=$item['melhor_percentual']===null ? null : round((float)$item['melhor_percentual'],1);
    $item['estado']=$item['tentativas']>0 ? 'concluida' : ($item['ultimo_acesso']!==null ? 'em_andamento' : 'disponivel');
    unset($item['ultimo_acesso']);
} unset($item);
responderJson(['status'=>'sucesso','atividades'=>$itens]);
