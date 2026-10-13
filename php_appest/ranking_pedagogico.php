<?php
// Regra mensal: cada conteúdo vale até cinco pontos, mesmo com várias unidades
// de livro. Em cada unidade considera-se a melhor tentativa do mês; a média
// dessas melhores marcas (unidades ainda não feitas contam zero) é arredondada.
// Tentativas anteriores à migração entram como linha de base, preservando os
// pontos já registrados sem acrescentá-los outra vez.
function pontuacaoDoConteudo(mysqli $conn,int $idAluno,int $idTurma,?int $idConteudo,int $idCapitulo,string $mes,bool $apenasLegado=false,?int $antesQuizId=null): int {
    if ($idConteudo!==null) {
        $s=$conn->prepare('SELECT cc.id_capitulo FROM conteudo_capitulo cc
            JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
            JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1
            JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo AND cp.materia=l.materia
            WHERE cc.id_conteudo=? ORDER BY cc.ordem');
        $s->bind_param('i',$idConteudo); $s->execute();
        $ids=array_map('intval',array_column($s->get_result()->fetch_all(MYSQLI_ASSOC),'id_capitulo')); $s->close();
    } else $ids=[$idCapitulo];
    if (!$ids) return 0;
    $inicio=$mes.'-01'; $fim=date('Y-m-d',strtotime($inicio.' +1 month'));
    $in=implode(',',array_fill(0,count($ids),'?'));
    $escopo=$idConteudo===null ? 'q.id_conteudo IS NULL' :
        ($apenasLegado ? 'q.id_conteudo IS NULL' : '(q.id_conteudo=? OR q.id_conteudo IS NULL)');
    $sql="SELECT e.id_capitulo,MAX(5.0*q.acertos/NULLIF(q.total,0)) AS melhor
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        WHERE q.id_aluno=? AND q.id_turma=? AND q.criado_em>=? AND q.criado_em<?".
        ($antesQuizId!==null ? ' AND q.id_quiz<?' : '')."
          AND $escopo AND e.id_capitulo IN ($in) GROUP BY e.id_capitulo";
    $s=$conn->prepare($sql);
    $args=[$idAluno,$idTurma,$inicio,$fim];
    if ($antesQuizId!==null) $args[]=$antesQuizId;
    if ($idConteudo!==null && !$apenasLegado) $args[]=$idConteudo;
    array_push($args,...$ids);
    $s->bind_param(str_repeat('s',count($args)),...$args); $s->execute();
    $soma=0.0;
    foreach ($s->get_result() as $linha) $soma+=(float)$linha['melhor'];
    $s->close();
    return min(5,max(0,(int)round($soma/count($ids),0,PHP_ROUND_HALF_UP)));
}

function atualizarRankingPorMelhor(mysqli $conn,int $idAluno,int $idTurma,?int $idConteudo,int $idCapitulo,int $idQuiz): void {
    // Usa o mesmo relógio do criado_em do quiz, inclusive na virada do mês.
    $mes=(string)$conn->query("SELECT DATE_FORMAT(NOW(),'%Y-%m') AS mes")->fetch_assoc()['mes'];
    $chave=$idConteudo!==null ? 'conteudo:'.$idConteudo : 'capitulo:'.$idCapitulo;
    $novo=pontuacaoDoConteudo($conn,$idAluno,$idTurma,$idConteudo,$idCapitulo,$mes);
    $legado=pontuacaoDoConteudo($conn,$idAluno,$idTurma,$idConteudo,$idCapitulo,$mes,true,
        $idConteudo===null ? $idQuiz : null);
    $s=$conn->prepare('SELECT pontos FROM ranking_melhor_conteudo
        WHERE id_aluno=? AND id_turma=? AND mes_referencia=? AND chave_conteudo=? FOR UPDATE');
    $s->bind_param('iiss',$idAluno,$idTurma,$mes,$chave); $s->execute();
    $anterior=$s->get_result()->fetch_assoc(); $s->close();
    $base=max($legado,(int)($anterior['pontos'] ?? 0));
    $diferenca=max(0,$novo-$base);
    $s=$conn->prepare('INSERT INTO ranking_melhor_conteudo(id_aluno,id_turma,mes_referencia,chave_conteudo,pontos)
        VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE pontos=GREATEST(pontos,VALUES(pontos))');
    $s->bind_param('iissi',$idAluno,$idTurma,$mes,$chave,$novo); $s->execute(); $s->close();
    if ($diferenca>0) {
        $s=$conn->prepare('INSERT INTO ranking(id_aluno,id_turma,pontos,mes_referencia) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE pontos=pontos+VALUES(pontos)');
        $s->bind_param('iiis',$idAluno,$idTurma,$diferenca,$mes); $s->execute(); $s->close();
    }
}
