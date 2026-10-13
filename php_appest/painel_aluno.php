<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Acesso exclusivo de alunos.',403);
$s=$conn->prepare('SELECT t.id_turma,t.nome_turma,t.serie FROM matricula m JOIN turma t ON t.id_turma=m.id_turma
    WHERE m.id_aluno=? ORDER BY t.ano_letivo DESC,t.id_turma DESC LIMIT 1');
$s->bind_param('i',$aluno['id_usuario']); $s->execute(); $turma=$s->get_result()->fetch_assoc(); $s->close();
if (!$turma) responderJson(['status'=>'sucesso','nome'=>$aluno['nome'],'turma'=>null,'serie'=>null,
    'mensagem'=>'A secretaria ainda não vinculou sua conta a uma turma.']);
$serie=trim((string)$turma['serie']);
if ($serie==='') responderJson(['status'=>'sucesso','nome'=>$aluno['nome'],'turma'=>$turma['nome_turma'],'serie'=>null,
    'mensagem'=>'A secretaria ainda não informou a série da sua turma.']);

$s=$conn->prepare('SELECT COUNT(*) AS total, SUM(x.unidades_concluidas=x.total_unidades) AS concluidos FROM (
    SELECT cp.id_conteudo,COUNT(DISTINCT c.id_capitulo) AS total_unidades,
    COUNT(DISTINCT CASE WHEN q.id_quiz IS NOT NULL THEN c.id_capitulo END) AS unidades_concluidas
    FROM conteudo_pedagogico cp JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
    JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
    JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
    LEFT JOIN estudo_gerado e ON e.id_capitulo=c.id_capitulo
    LEFT JOIN quiz_estudo q ON q.id_estudo=e.id_estudo AND q.id_aluno=?
    WHERE cp.serie=? AND cp.ativo=1 GROUP BY cp.id_conteudo
) x');
$s->bind_param('is',$aluno['id_usuario'],$serie); $s->execute(); $contagem=$s->get_result()->fetch_assoc(); $s->close();

$s=$conn->prepare('SELECT COUNT(*) AS realizados,ROUND(AVG(100.0*acertos/NULLIF(total,0)),1) AS media
    FROM quiz_estudo WHERE id_aluno=?');
$s->bind_param('i',$aluno['id_usuario']); $s->execute(); $quizzes=$s->get_result()->fetch_assoc(); $s->close();

$s=$conn->prepare('SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre
    FROM progresso_conteudo p JOIN conteudo_pedagogico cp ON cp.id_conteudo=p.id_conteudo
    JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
    JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
    JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
    WHERE p.id_aluno=? AND cp.serie=? AND cp.ativo=1
    GROUP BY cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,p.ultimo_acesso
    ORDER BY p.ultimo_acesso DESC LIMIT 1');
$s->bind_param('is',$aluno['id_usuario'],$serie); $s->execute(); $continuar=$s->get_result()->fetch_assoc(); $s->close();

$s=$conn->prepare('SELECT COUNT(*) AS total FROM atividade_publicada a
    JOIN conteudo_pedagogico cp ON cp.id_conteudo=a.id_conteudo AND cp.serie=? AND cp.ativo=1
    WHERE a.id_turma=? AND a.ativa=1 AND
      (SELECT COUNT(DISTINCT e.id_capitulo) FROM atividade_quiz aq
        JOIN quiz_estudo q ON q.id_quiz=aq.id_quiz AND q.id_aluno=?
        JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        WHERE aq.id_atividade=a.id_atividade) <
      (SELECT COUNT(*) FROM conteudo_capitulo cc
        JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
        JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
        WHERE cc.id_conteudo=cp.id_conteudo)');
$s->bind_param('sii',$serie,$turma['id_turma'],$aluno['id_usuario']); $s->execute();
$atividades=(int)$s->get_result()->fetch_assoc()['total']; $s->close();

$total=(int)$contagem['total']; $concluidos=(int)($contagem['concluidos'] ?? 0);
responderJson(['status'=>'sucesso','nome'=>$aluno['nome'],'turma'=>$turma['nome_turma'],
    'serie'=>$serie,'id_turma'=>(int)$turma['id_turma'],
    'conteudos_disponiveis'=>$total,'conteudos_concluidos'=>$concluidos,
    'progresso_percentual'=>$total>0 ? round(100*$concluidos/$total,1) : null,
    'quizzes_realizados'=>(int)$quizzes['realizados'],
    'aproveitamento_medio'=>$quizzes['media']===null ? null : (float)$quizzes['media'],
    'atividades_pendentes'=>$atividades,
    'continuar'=>$continuar ? [
        'id_conteudo'=>(int)$continuar['id_conteudo'],'nome'=>$continuar['nome'],
        'materia'=>$continuar['materia'],'bimestre'=>$continuar['bimestre']===null ? null : (int)$continuar['bimestre']
    ] : null]);
