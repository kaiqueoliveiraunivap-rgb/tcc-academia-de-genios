<?php
// Consulta compartilhada pelos fluxos de estudo livre e atividade publicada.
function unidadesDoConteudoAluno(mysqli $conn,int $idAluno,int $idTurma,int $idConteudo): array {
    $s=$conn->prepare('SELECT cp.id_conteudo,cp.nome,cp.materia,cp.bimestre,cc.id_capitulo
        FROM conteudo_pedagogico cp
        JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
        JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
        JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
        JOIN capitulo_livro c ON c.id_capitulo=cc.id_capitulo AND c.revisado=1
        JOIN livro_didatico l ON l.id_livro=c.id_livro AND l.ativo=1 AND l.materia=cp.materia
        WHERE cp.id_conteudo=? AND cp.ativo=1
        ORDER BY cc.ordem,c.ordem,c.id_capitulo');
    $s->bind_param('iii',$idTurma,$idAluno,$idConteudo); $s->execute();
    $linhas=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    if (!$linhas) return [];
    $capitulos=array_values(array_unique(array_map('intval',array_column($linhas,'id_capitulo'))));
    return ['id_conteudo'=>$idConteudo,'nome'=>$linhas[0]['nome'],'materia'=>$linhas[0]['materia'],
        'bimestre'=>$linhas[0]['bimestre']===null ? null : (int)$linhas[0]['bimestre'],
        'unidades'=>$capitulos];
}

function acessarConteudoAluno(mysqli $conn,int $idAluno,array $conteudo,?int $idCapitulo=null): array {
    $unidades=$conteudo['unidades'];
    if ($idCapitulo!==null && !in_array($idCapitulo,$unidades,true)) return [];
    $primeiro=$unidades[0]; $idConteudo=$conteudo['id_conteudo'];
    if ($idCapitulo!==null) {
        $s=$conn->prepare('INSERT INTO progresso_conteudo(id_aluno,id_conteudo,id_capitulo_atual)
            VALUES (?,?,?) ON DUPLICATE KEY UPDATE id_capitulo_atual=VALUES(id_capitulo_atual),ultimo_acesso=NOW()');
        $s->bind_param('iii',$idAluno,$idConteudo,$idCapitulo); $s->execute(); $s->close();
        $atual=$idCapitulo;
    } else {
        $s=$conn->prepare('INSERT INTO progresso_conteudo(id_aluno,id_conteudo,id_capitulo_atual)
            VALUES (?,?,?) ON DUPLICATE KEY UPDATE ultimo_acesso=NOW()');
        $s->bind_param('iii',$idAluno,$idConteudo,$primeiro); $s->execute(); $s->close();
        $s=$conn->prepare('SELECT id_capitulo_atual FROM progresso_conteudo WHERE id_aluno=? AND id_conteudo=?');
        $s->bind_param('ii',$idAluno,$idConteudo); $s->execute();
        $atual=(int)$s->get_result()->fetch_assoc()['id_capitulo_atual']; $s->close();
        if (!in_array($atual,$unidades,true)) $atual=$primeiro;
    }
    $conteudo['id_capitulo']=$atual;
    $conteudo['unidade_atual']=array_search($atual,$unidades,true)+1;
    return $conteudo;
}
