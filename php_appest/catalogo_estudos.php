<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';

$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Acesso exclusivo de alunos.',403);
$acao=$_POST['acao'] ?? 'materias';
if (!in_array($acao,['materias','conteudos','abrir'],true)) responderErro('Ação inválida.',422);
$idTurma=inteiroDoPost('id_turma');
if (isset($_POST['id_turma']) && !$idTurma) responderErro('Turma inválida.',422);

// A série vem da matrícula. Nenhum campo enviado pelo aplicativo define acesso.
$sql='SELECT t.id_turma,t.nome_turma,t.serie FROM matricula m
      JOIN turma t ON t.id_turma=m.id_turma WHERE m.id_aluno=?';
if ($idTurma) $sql.=' AND t.id_turma=?';
$sql.=' ORDER BY t.ano_letivo DESC,t.id_turma DESC LIMIT 1';
$s=$conn->prepare($sql);
if ($idTurma) $s->bind_param('ii',$aluno['id_usuario'],$idTurma);
else $s->bind_param('i',$aluno['id_usuario']);
$s->execute(); $turma=$s->get_result()->fetch_assoc(); $s->close();
if (!$turma) responderErro('Aluno sem matrícula nessa turma.',403);
$serie=trim((string)$turma['serie']);
if ($serie==='') responderJson(['status'=>'sucesso','serie'=>null,'turma'=>$turma['nome_turma'],'materias'=>[],'conteudos'=>[],'mensagem'=>'A secretaria ainda não informou a série desta turma.']);

if ($acao==='abrir') {
    $idConteudo=inteiroDoPost('id_conteudo');
    if (!$idConteudo) responderErro('Conteúdo inválido.',422);
    $s=$conn->prepare('SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cc.id_capitulo
      FROM conteudo_pedagogico cp JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
      JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
      JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
      WHERE cp.id_conteudo=? AND cp.serie=? AND cp.ativo=1
      ORDER BY cc.ordem,c.ordem,c.id_capitulo LIMIT 1');
    $s->bind_param('is',$idConteudo,$serie); $s->execute(); $conteudo=$s->get_result()->fetch_assoc(); $s->close();
    if (!$conteudo) responderErro('Conteúdo indisponível para sua série.',403);
    $s=$conn->prepare('INSERT INTO progresso_conteudo (id_aluno,id_conteudo,id_capitulo_atual)
      VALUES (?,?,?) ON DUPLICATE KEY UPDATE ultimo_acesso=NOW()');
    $s->bind_param('iii',$aluno['id_usuario'],$idConteudo,$conteudo['id_capitulo']); $s->execute(); $s->close();
    $s=$conn->prepare('SELECT p.id_capitulo_atual FROM progresso_conteudo p
      JOIN conteudo_capitulo cc ON cc.id_conteudo=p.id_conteudo AND cc.id_capitulo=p.id_capitulo_atual
      WHERE p.id_aluno=? AND p.id_conteudo=?');
    $s->bind_param('ii',$aluno['id_usuario'],$idConteudo); $s->execute();
    $capituloAtual=$s->get_result()->fetch_assoc(); $s->close();
    responderJson(['status'=>'sucesso','conteudo'=>[
        'id_conteudo'=>$idConteudo,'nome'=>$conteudo['nome'],'materia'=>$conteudo['materia'],
        'bimestre'=>$conteudo['bimestre']===null ? null : (int)$conteudo['bimestre'],
        'id_capitulo'=>(int)($capituloAtual['id_capitulo_atual'] ?? $conteudo['id_capitulo'])
    ]]);
}

// Somente capítulos revisados de livros ativos entram no catálogo do aluno.
// Um conteúdo com várias unidades fica concluído quando todas possuem quiz.
$sql='SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cp.ordem,
      COUNT(DISTINCT c.id_capitulo) AS total_unidades,
      COUNT(DISTINCT CASE WHEN q.id_quiz IS NOT NULL THEN c.id_capitulo END) AS unidades_concluidas,
      COUNT(DISTINCT q.id_quiz) AS tentativas,
      ROUND(MAX(100.0*q.acertos/NULLIF(q.total,0)),1) AS melhor_percentual,
      MAX(p.ultimo_acesso) AS ultimo_acesso
      FROM conteudo_pedagogico cp
      JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
      JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
      JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
      LEFT JOIN estudo_gerado e ON e.id_capitulo=c.id_capitulo
      LEFT JOIN quiz_estudo q ON q.id_estudo=e.id_estudo AND q.id_aluno=?
      LEFT JOIN progresso_conteudo p ON p.id_conteudo=cp.id_conteudo AND p.id_aluno=?
      WHERE cp.serie=? AND cp.ativo=1';
$materia=trim((string)($_POST['materia'] ?? ''));
if ($acao==='conteudos') {
    if ($materia==='' || mb_strlen($materia)>80) responderErro('Matéria inválida.',422);
    $sql.=' AND cp.materia=?';
}
$sql.=' GROUP BY cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cp.ordem
       ORDER BY cp.materia,cp.bimestre IS NULL,cp.bimestre,cp.ordem,cp.nome';
$s=$conn->prepare($sql);
if ($acao==='conteudos') $s->bind_param('iiss',$aluno['id_usuario'],$aluno['id_usuario'],$serie,$materia);
else $s->bind_param('iis',$aluno['id_usuario'],$aluno['id_usuario'],$serie);
$s->execute(); $linhas=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
$materias=[]; $conteudos=[];
foreach ($linhas as $item) {
    $item['id_conteudo']=(int)$item['id_conteudo'];
    $item['bimestre']=$item['bimestre']===null ? null : (int)$item['bimestre'];
    $item['total_unidades']=(int)$item['total_unidades'];
    $item['unidades_concluidas']=(int)$item['unidades_concluidas'];
    $item['tentativas']=(int)$item['tentativas'];
    $item['melhor_percentual']=$item['melhor_percentual']===null ? null : (float)$item['melhor_percentual'];
    $item['estado']=$item['unidades_concluidas']===$item['total_unidades'] ? 'concluido'
        : ($item['ultimo_acesso']!==null || $item['tentativas']>0 ? 'em_andamento' : 'nao_iniciado');
    if (!isset($materias[$item['materia']])) $materias[$item['materia']]=['nome'=>$item['materia'],'total'=>0,'concluidos'=>0];
    $materias[$item['materia']]['total']++;
    if ($item['estado']==='concluido') $materias[$item['materia']]['concluidos']++;
    $conteudos[]=$item;
}
responderJson(['status'=>'sucesso','serie'=>$serie,'turma'=>$turma['nome_turma'],
    'id_turma'=>(int)$turma['id_turma'],'materias'=>array_values($materias),
    'conteudos'=>$acao==='conteudos' ? $conteudos : []]);
