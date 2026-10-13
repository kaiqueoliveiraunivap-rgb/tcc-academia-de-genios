<?php
// Classificação do mês atual: melhor quiz de cada unidade, até cinco pontos
// por conteúdo. Históricos antigos continuam nas tabelas originais.
function rankingJustoDoMes(mysqli $conn,string $mes,?int $idTurma,?string $serie): array {
    $inicio=$mes.'-01'; $fim=date('Y-m-d',strtotime($inicio.' +1 month'));
    $sql='SELECT q.id_aluno,q.id_turma,t.serie,u.nome,q.id_conteudo,e.id_capitulo,
        MAX(5.0*q.acertos/NULLIF(q.total,0)) AS melhor
        FROM quiz_estudo q JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
        JOIN turma t ON t.id_turma=q.id_turma JOIN usuario u ON u.id_usuario=q.id_aluno
        WHERE q.criado_em>=? AND q.criado_em<? AND q.total>0';
    if ($idTurma!==null) $sql.=' AND q.id_turma=?';
    else $sql.=' AND t.serie=?';
    $sql.=' GROUP BY q.id_aluno,q.id_turma,t.serie,u.nome,q.id_conteudo,e.id_capitulo';
    $s=$conn->prepare($sql);
    if ($idTurma!==null) $s->bind_param('ssi',$inicio,$fim,$idTurma);
    else $s->bind_param('sss',$inicio,$fim,$serie);
    $s->execute(); $tentativas=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    if (!$tentativas) return [];

    // Capítulos podem ser compartilhados entre conteúdos ou séries.
    // Só atribuímos tentativa antiga sem id_conteudo se houver um vínculo único.
    $s=$conn->prepare('SELECT cp.id_conteudo,cp.serie,cc.id_capitulo
        FROM conteudo_capitulo cc JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo');
    $s->execute(); $vinculos=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $porCapitulo=[]; $unidades=[];
    foreach ($vinculos as $v) {
        $id=(int)$v['id_conteudo']; $cap=(int)$v['id_capitulo'];
        $porCapitulo[$v['serie'].'|'.$cap][$id]=true;
        $unidades[$id][$cap]=true;
    }
    $explicitos=[];
    foreach ($tentativas as $t) if ($t['id_conteudo']!==null) {
        $escopo=$idTurma===null ? 'serie' : $t['id_turma'];
        $explicitos[$t['id_aluno'].'|'.$escopo.'|'.$t['id_capitulo']]=true;
    }
    $grupos=[]; $nomes=[];
    foreach ($tentativas as $t) {
        $aluno=(int)$t['id_aluno']; $turma=(int)$t['id_turma']; $cap=(int)$t['id_capitulo'];
        $ids=$porCapitulo[$t['serie'].'|'.$cap] ?? [];
        $idConteudo=$t['id_conteudo']===null ? null : (int)$t['id_conteudo'];
        if ($idConteudo===null && count($ids)===1) $idConteudo=(int)array_key_first($ids);
        $escopo=$idTurma===null ? 'serie' : $turma;
        if ($idConteudo===null && count($ids)>1 && isset($explicitos[$aluno.'|'.$escopo.'|'.$cap])) continue;
        $chave=$idConteudo===null ? 'capitulo:'.$cap : 'conteudo:'.$idConteudo;
        // Na classificação da série, duas matrículas da mesma série não
        // multiplicam o mesmo conteúdo estudado pelo aluno naquele mês.
        $grupo=$aluno.'|'.($idTurma===null ? 'serie' : $turma).'|'.$chave;
        $grupos[$grupo]['aluno']=$aluno; $grupos[$grupo]['turma']=$turma;
        $grupos[$grupo]['conteudo']=$idConteudo;
        $grupos[$grupo]['melhores'][$cap]=max((float)($grupos[$grupo]['melhores'][$cap] ?? 0),(float)$t['melhor']);
        $nomes[$aluno]=$t['nome'];
    }
    $pontos=[];
    foreach ($grupos as $g) {
        $total=$g['conteudo']===null ? 1 : max(1,count($unidades[$g['conteudo']] ?? []));
        $marca=min(5,max(0,(int)round(array_sum($g['melhores'])/$total,0,PHP_ROUND_HALF_UP)));
        $pontos[$g['aluno']]=($pontos[$g['aluno']] ?? 0)+$marca;
    }
    $lista=[];
    foreach ($pontos as $id=>$valor) $lista[]=['id_usuario'=>(int)$id,'nome'=>$nomes[$id],'pontos'=>$valor,'mes_referencia'=>$mes];
    usort($lista,static fn($a,$b)=>$b['pontos']<=>$a['pontos'] ?: strcasecmp($a['nome'],$b['nome']) ?: $a['id_usuario']<=>$b['id_usuario']);
    return $lista;
}
