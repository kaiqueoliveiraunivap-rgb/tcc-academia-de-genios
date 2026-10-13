<?php
require_once __DIR__.'/auth.php';
$professor=exigirProfessor($conn);
$idProfessor=(int)$professor['id_usuario'];
$acao=$_POST['acao'] ?? 'resumo';
if (!in_array($acao,['resumo','conteudos','detalhe','salvar_orientacao','publicar_atividade'],true)) responderErro('Ação inválida.',422);

function professorPodeMateria(mysqli $conn,int $idProfessor,string $materia): bool {
    $s=$conn->prepare('SELECT COUNT(*) AS total,MAX(materia=?) AS vinculada FROM professor_materia WHERE id_professor=?');
    $s->bind_param('si',$materia,$idProfessor); $s->execute();
    $r=$s->get_result()->fetch_assoc(); $s->close();
    return (int)$r['total']===0 || (int)$r['vinculada']===1;
}

function conteudoDoProfessor(mysqli $conn,int $idProfessor,int $idTurma,int $idConteudo): array {
    exigirTurmaDoProfessor($conn,$idTurma,$idProfessor);
    $s=$conn->prepare('SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cp.serie,t.nome_turma,t.ano_letivo
        FROM conteudo_pedagogico cp JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
        WHERE cp.id_conteudo=? AND cp.ativo=1 AND EXISTS (
            SELECT 1 FROM conteudo_capitulo cc JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
            JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
            WHERE cc.id_conteudo=cp.id_conteudo) LIMIT 1');
    $s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $conteudo=$s->get_result()->fetch_assoc(); $s->close();
    if (!$conteudo || !professorPodeMateria($conn,$idProfessor,$conteudo['materia'])) responderErro('Conteúdo indisponível para este professor.',403);
    return $conteudo;
}

if ($acao==='resumo') {
    $s=$conn->prepare('SELECT COUNT(DISTINCT t.id_turma) AS turmas,COUNT(DISTINCT m.id_aluno) AS alunos
        FROM turma t LEFT JOIN matricula m ON m.id_turma=t.id_turma WHERE t.id_professor=?');
    $s->bind_param('i',$idProfessor); $s->execute(); $contagem=$s->get_result()->fetch_assoc(); $s->close();
    $s=$conn->prepare('SELECT COUNT(DISTINCT a.id_atividade) AS publicadas,
        COUNT(DISTINCT aq.id_atividade,q.id_aluno) AS conclusoes
        FROM atividade_publicada a LEFT JOIN atividade_quiz aq ON aq.id_atividade=a.id_atividade
        LEFT JOIN quiz_estudo q ON q.id_quiz=aq.id_quiz WHERE a.id_professor=?');
    $s->bind_param('i',$idProfessor); $s->execute(); $atividades=$s->get_result()->fetch_assoc(); $s->close();
    $s=$conn->prepare('SELECT COUNT(*) AS quizzes,ROUND(AVG(100.0*q.acertos/NULLIF(q.total,0)),1) AS media
        FROM quiz_estudo q JOIN turma t ON t.id_turma=q.id_turma WHERE t.id_professor=?');
    $s->bind_param('i',$idProfessor); $s->execute(); $quizzes=$s->get_result()->fetch_assoc(); $s->close();
    $s=$conn->prepare('SELECT t.id_turma,t.nome_turma,t.serie,t.ano_letivo,
        (SELECT COUNT(*) FROM matricula m WHERE m.id_turma=t.id_turma) AS alunos,
        (SELECT COUNT(*) FROM atividade_publicada a WHERE a.id_turma=t.id_turma AND a.ativa=1) AS atividades
        FROM turma t WHERE t.id_professor=? ORDER BY t.ano_letivo DESC,t.serie,t.nome_turma');
    $s->bind_param('i',$idProfessor); $s->execute(); $turmas=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    foreach ($turmas as &$t) foreach (['id_turma','alunos','atividades'] as $campo) $t[$campo]=(int)$t[$campo]; unset($t);
    responderJson(['status'=>'sucesso','nome'=>$professor['nome'],
        'turmas_total'=>(int)$contagem['turmas'],'alunos_total'=>(int)$contagem['alunos'],
        'atividades_publicadas'=>(int)$atividades['publicadas'],
        'atividades_concluidas'=>(int)$atividades['conclusoes'],
        'quizzes_realizados'=>(int)$quizzes['quizzes'],
        'aproveitamento_medio'=>$quizzes['media']===null ? null : (float)$quizzes['media'],
        'turmas'=>$turmas]);
}

$idTurma=inteiroDoPost('id_turma');
if (!$idTurma) responderErro('Turma inválida.',422);
$turma=exigirTurmaDoProfessor($conn,$idTurma,$idProfessor);
if ($acao==='conteudos') {
    $s=$conn->prepare('SELECT serie,nome_turma,ano_letivo FROM turma WHERE id_turma=?');
    $s->bind_param('i',$idTurma); $s->execute(); $turma=$s->get_result()->fetch_assoc(); $s->close();
    $s=$conn->prepare('SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cp.ordem,
        (SELECT COUNT(*) FROM atividade_publicada a WHERE a.id_conteudo=cp.id_conteudo AND a.id_turma=? AND a.ativa=1) AS atividades
        FROM conteudo_pedagogico cp WHERE cp.serie=? AND cp.ativo=1 AND EXISTS (
            SELECT 1 FROM conteudo_capitulo cc JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
            JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
            WHERE cc.id_conteudo=cp.id_conteudo)
        ORDER BY cp.materia,cp.bimestre IS NULL,cp.bimestre,cp.ordem,cp.nome');
    $s->bind_param('is',$idTurma,$turma['serie']); $s->execute(); $itens=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $itens=array_values(array_filter($itens,fn($c)=>professorPodeMateria($conn,$idProfessor,$c['materia'])));
    foreach ($itens as &$item) {
        $item['id_conteudo']=(int)$item['id_conteudo']; $item['atividades']=(int)$item['atividades'];
        $item['bimestre']=$item['bimestre']===null ? null : (int)$item['bimestre'];
    } unset($item);
    responderJson(['status'=>'sucesso','turma'=>$turma,'conteudos'=>$itens]);
}
$idConteudo=inteiroDoPost('id_conteudo');
if (!$idConteudo) responderErro('Conteúdo inválido.',422);
$conteudo=conteudoDoProfessor($conn,$idProfessor,$idTurma,$idConteudo);

if ($acao==='salvar_orientacao') {
    $instrucao=is_string($_POST['instrucao'] ?? null) ? trim($_POST['instrucao']) : '';
    if ($instrucao==='' || mb_strlen($instrucao)>1000) responderErro('Escreva uma orientação de até 1.000 caracteres.',422);
    $promptFinal="Turma: ".$conteudo['nome_turma'].". Série: ".$conteudo['serie'].". Matéria: ".$conteudo['materia'].". Conteúdo: ".$conteudo['nome'].".\nOrientação do professor: ".$instrucao."\nExplique primeiro com base nas páginas fornecidas e formule cinco questões coerentes com o conteúdo.";
    try {
        $conn->begin_transaction();
        $s=$conn->prepare('SELECT id_turma FROM turma WHERE id_turma=? AND id_professor=? FOR UPDATE');
        $s->bind_param('ii',$idTurma,$idProfessor); $s->execute();
        if (!$s->get_result()->fetch_assoc()) throw new RuntimeException('Turma alterada.'); $s->close();
        $s=$conn->prepare('SELECT id_prompt,instrucao FROM prompt_professor
            WHERE id_turma=? AND id_conteudo=? AND id_aluno IS NULL AND usado=0 ORDER BY id_prompt DESC LIMIT 1');
        $s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $atual=$s->get_result()->fetch_assoc(); $s->close();
        if ($atual && $atual['instrucao']===$instrucao) $idPrompt=(int)$atual['id_prompt'];
        else {
            $s=$conn->prepare('UPDATE prompt_professor SET usado=1 WHERE id_turma=? AND id_conteudo=? AND id_aluno IS NULL AND usado=0');
            $s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $s->close();
            $s=$conn->prepare("INSERT INTO prompt_professor(id_professor,id_aluno,id_turma,materia,id_conteudo,bimestre,dificuldade,instrucao,prompt_final)
                VALUES (?,NULL,?,?,?,?, 'MEDIO',?,?)");
            $s->bind_param('iisiiss',$idProfessor,$idTurma,$conteudo['materia'],$idConteudo,$conteudo['bimestre'],$instrucao,$promptFinal);
            $s->execute(); $idPrompt=(int)$s->insert_id; $s->close();
        }
        $conn->commit();
        responderJson(['status'=>'sucesso','id_prompt'=>$idPrompt,'instrucao'=>$instrucao]);
    } catch (Throwable $e) {
        $conn->rollback(); error_log('Orientação pedagógica: '.$e->getMessage());
        responderErro('Não foi possível salvar a orientação.',503);
    }
}

if ($acao==='publicar_atividade') {
    $titulo=is_string($_POST['titulo'] ?? null) ? trim($_POST['titulo']) : '';
    if ($titulo==='' || mb_strlen($titulo)>180) responderErro('Informe um título de até 180 caracteres.',422);
    $s=$conn->prepare('INSERT INTO atividade_publicada(id_conteudo,id_turma,id_professor,titulo)
        VALUES (?,?,?,?)');
    $s->bind_param('iiis',$idConteudo,$idTurma,$idProfessor,$titulo); $s->execute();
    $id=(int)$s->insert_id; $s->close();
    responderJson(['status'=>'sucesso','id_atividade'=>$id]);
}

$s=$conn->prepare('SELECT p.id_prompt,p.instrucao,p.data_criacao
    FROM prompt_professor p WHERE p.id_turma=? AND p.id_conteudo=? AND p.id_aluno IS NULL AND p.usado=0
    ORDER BY p.id_prompt DESC LIMIT 1');
$s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $orientacao=$s->get_result()->fetch_assoc(); $s->close();
$s=$conn->prepare('SELECT a.id_atividade,a.titulo,a.publicada_em,
    COUNT(DISTINCT aq.id_quiz) AS tentativas,COUNT(DISTINCT q.id_aluno) AS concluintes
    FROM atividade_publicada a LEFT JOIN atividade_quiz aq ON aq.id_atividade=a.id_atividade
    LEFT JOIN quiz_estudo q ON q.id_quiz=aq.id_quiz
    WHERE a.id_turma=? AND a.id_conteudo=? AND a.ativa=1
    GROUP BY a.id_atividade,a.titulo,a.publicada_em ORDER BY a.publicada_em DESC');
$s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $atividades=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
foreach ($atividades as &$a) foreach (['id_atividade','tentativas','concluintes'] as $campo) $a[$campo]=(int)$a[$campo]; unset($a);
$s=$conn->prepare('SELECT COUNT(DISTINCT q.id_aluno) AS alunos,COUNT(r.id_resposta) AS respostas,
    SUM(r.acertou) AS acertos FROM quiz_estudo q JOIN resposta_estudo r ON r.id_quiz=q.id_quiz
    WHERE q.id_turma=? AND q.id_conteudo=?');
$s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $desempenho=$s->get_result()->fetch_assoc(); $s->close();
$s=$conn->prepare('SELECT r.assunto,COUNT(*) AS respostas,SUM(r.acertou) AS acertos
    FROM quiz_estudo q JOIN resposta_estudo r ON r.id_quiz=q.id_quiz
    WHERE q.id_turma=? AND q.id_conteudo=? GROUP BY r.assunto
    ORDER BY (COUNT(*)-SUM(r.acertou)) DESC,COUNT(*) DESC LIMIT 5');
$s->bind_param('ii',$idTurma,$idConteudo); $s->execute(); $dificuldades=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
$respostas=(int)$desempenho['respostas'];
responderJson(['status'=>'sucesso','conteudo'=>$conteudo,'orientacao'=>$orientacao,
    'atividades'=>$atividades,'desempenho'=>[
        'alunos'=>(int)$desempenho['alunos'],'respostas'=>$respostas,'acertos'=>(int)($desempenho['acertos'] ?? 0),
        'aproveitamento'=>$respostas ? round(100*(int)$desempenho['acertos']/$respostas,1) : null
    ],'dificuldades'=>$dificuldades]);
