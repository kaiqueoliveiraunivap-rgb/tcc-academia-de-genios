<?php
// Consulta somente os dados do dono do token. Não aceita id de usuário do cliente.
require_once 'auth.php';
header('Cache-Control: no-store');
$usuario=exigirUsuarioLogado($conn);
$perfil=strtolower($usuario['tipo_perfil']);
if (!in_array($perfil,['aluno','professor'],true)) responderErro('Perfil não disponível no aplicativo.',403);
$id=(int)$usuario['id_usuario'];
if ($perfil==='aluno') {
    $sql='SELECT t.nome_turma,t.serie,t.ano_letivo FROM matricula m JOIN turma t ON t.id_turma=m.id_turma WHERE m.id_aluno=? ORDER BY t.ano_letivo DESC,t.nome_turma';
} else {
    $sql='SELECT t.nome_turma,t.serie,t.ano_letivo FROM turma t WHERE t.id_professor=? ORDER BY t.ano_letivo DESC,t.nome_turma';
}
$stmt=$conn->prepare($sql);$stmt->bind_param('i',$id);$stmt->execute();
$turmas=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
responderJson(['status'=>'sucesso','usuario'=>[
    'id_usuario'=>$id,'nome'=>$usuario['nome'],'email'=>$usuario['email'],'tipo_perfil'=>$perfil
],'turmas'=>$turmas]);
