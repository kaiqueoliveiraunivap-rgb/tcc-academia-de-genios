<?php
// Consulta a orientação ativa da turma. Nenhum aluno a consome ao estudar.
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';
require_once __DIR__.'/orientacao_turma.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Consulta exclusiva de alunos.',403);
$materia=is_string($_POST['materia'] ?? null) ? trim($_POST['materia']) : '';
if ($materia==='' || mb_strlen($materia)>100) responderErro('Informe a matéria.',422);
$idTurma=turmaDoEstudo($conn,$aluno['id_usuario'],inteiroDoPost('id_turma'));
$orientacao=orientacaoDaTurma($conn,$idTurma,$materia,inteiroDoPost('id_livro'));
if (!$orientacao) responderJson(['status'=>'vazio']);
$orientacao['id_prompt']=(int)$orientacao['id_prompt'];
$orientacao['id_turma']=(int)$orientacao['id_turma'];
$orientacao['dificuldade']='MEDIO';
responderJson(['status'=>'sucesso','prompt'=>$orientacao]);
