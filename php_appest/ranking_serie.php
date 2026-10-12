<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';

$usuario=exigirUsuarioLogado($conn);
$perfil=normalizarPerfil($usuario['tipo_perfil']);
$serie=trim((string)($_POST['serie'] ?? ''));
$idTurma=inteiroDoPost('id_turma');
$mes=trim((string)($_POST['mes_referencia'] ?? ''));

if (!in_array($perfil,['aluno','professor'],true)) responderErro('Perfil sem acesso ao ranking.',403);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$mes)) responderErro('Mês inválido.',422);

// Ao vir da tela de resultado, o app possui a turma. O servidor descobre a
// série e aplica as mesmas regras usadas pelo botão de ranking do menu.
if ($serie==='') {
    if (!$idTurma) responderErro('Turma ou série inválida.',422);
    if ($perfil==='aluno') {
        $s=$conn->prepare('SELECT t.serie FROM turma t JOIN matricula m ON m.id_turma=t.id_turma WHERE t.id_turma=? AND m.id_aluno=? LIMIT 1');
    } else {
        $s=$conn->prepare('SELECT t.serie FROM turma t WHERE t.id_turma=? AND t.id_professor=? LIMIT 1');
    }
    $s->bind_param('ii',$idTurma,$usuario['id_usuario']); $s->execute();
    $resultadoTurma=$s->get_result(); $turma=$resultadoTurma->fetch_assoc();
    $resultadoTurma->free(); $s->close();
    if (!$turma) responderErro('Turma não vinculada ao usuário.',403);
    $serie=trim((string)$turma['serie']);
}

if ($serie==='' || mb_strlen($serie)>80) responderErro('Série inválida.',422);
if ($perfil==='aluno') {
    $s=$conn->prepare('SELECT 1 FROM matricula m JOIN turma t ON t.id_turma=m.id_turma WHERE m.id_aluno=? AND t.serie=? LIMIT 1');
    $s->bind_param('is',$usuario['id_usuario'],$serie); $s->execute();
    $resultadoVinculo=$s->get_result(); $vinculado=$resultadoVinculo->num_rows===1;
    $resultadoVinculo->free(); $s->close();
    if (!$vinculado) responderErro('Série não vinculada ao aluno.',403);
}

$s=$conn->prepare("SELECT u.id_usuario,u.nome,SUM(r.pontos) AS pontos
    FROM ranking r
    JOIN usuario u ON u.id_usuario=r.id_aluno
    JOIN turma t ON t.id_turma=r.id_turma
    WHERE t.serie=? AND r.mes_referencia=?
    GROUP BY u.id_usuario,u.nome
    ORDER BY pontos DESC,u.nome,u.id_usuario");
$s->bind_param('ss',$serie,$mes); $s->execute(); $resultadoRanking=$s->get_result();
$lista=[];
while ($item=$resultadoRanking->fetch_assoc()) {
    $item['id_usuario']=(int)$item['id_usuario'];
    $item['pontos']=(int)$item['pontos'];
    $item['proprio']=$item['id_usuario']===(int)$usuario['id_usuario'];
    $lista[]=$item;
}

responderJson(['status'=>'sucesso','serie'=>$serie,'mes_referencia'=>$mes,'ranking'=>$lista]);
