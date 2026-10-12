<?php
require_once __DIR__.'/auth.php';
$u=exigirUsuarioLogado($conn); $perfil=strtolower($u['tipo_perfil']);
if ($perfil==='aluno') {
    $s=$conn->prepare("SELECT DISTINCT t.id_turma,t.nome_turma,t.serie,t.ano_letivo FROM turma t JOIN matricula m ON m.id_turma=t.id_turma WHERE m.id_aluno=? ORDER BY t.serie,t.nome_turma");
    $s->bind_param('i',$u['id_usuario']);
} elseif ($perfil==='professor') {
    $s=$conn->prepare("SELECT t.id_turma,t.nome_turma,t.serie,t.ano_letivo FROM turma t WHERE t.id_professor=? ORDER BY t.serie,t.nome_turma");
    $s->bind_param('i',$u['id_usuario']);
} else responderJson(['status'=>'sucesso','turmas'=>[],'series'=>[],'materias'=>[]]);
$s->execute(); $resultado=$s->get_result(); $turmas=[]; $series=[];
while($x=$resultado->fetch_assoc()) { $x['id_turma']=(int)$x['id_turma']; $turmas[]=$x; $series[$x['serie']]=$x['serie']; }
$materias=[];
if ($perfil==='professor') {
    $s=$conn->prepare("SELECT DISTINCT h.materia FROM historico h JOIN matricula m ON m.id_aluno=h.id_aluno JOIN turma t ON t.id_turma=m.id_turma WHERE t.id_professor=? AND h.materia IS NOT NULL ORDER BY h.materia");
    $s->bind_param('i',$u['id_usuario']); $s->execute(); $resultado=$s->get_result(); while($x=$resultado->fetch_assoc()) $materias[]=$x['materia'];
}
responderJson(['status'=>'sucesso','turmas'=>$turmas,'series'=>array_values($series),'materias'=>$materias]);
