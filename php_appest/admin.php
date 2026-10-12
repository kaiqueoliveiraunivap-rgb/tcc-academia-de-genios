<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/perfis.php';
$usuario = exigirUsuarioLogado($conn);
if (normalizarPerfil($usuario['tipo_perfil']) !== 'admin') responderErro('Acesso exclusivo da coordenação.', 403);
$acao = $_POST['acao'] ?? 'painel';
if ($acao === 'painel') {
    $contas = ['aluno'=>0, 'professor'=>0, 'admin'=>0];
    $r=$conn->query('SELECT tipo_perfil,COUNT(*) AS quantidade FROM usuario GROUP BY tipo_perfil');
    while ($row=$r->fetch_assoc()) {
        $perfil=normalizarPerfil($row['tipo_perfil']);
        if ($perfil) $contas[$perfil]+=(int)$row['quantidade'];
    }
    $livros=$conn->query('SELECT COUNT(*) AS n FROM livro_didatico WHERE ativo=1')->fetch_assoc()['n'];
    $capitulos=$conn->query('SELECT COUNT(*) AS n FROM capitulo_livro c JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE l.ativo=1 AND c.revisado=1')->fetch_assoc()['n'];
    responderJson(['status'=>'sucesso','contas'=>$contas,'livros'=>(int)$livros,'capitulos'=>(int)$capitulos]);
}
if ($acao === 'convite_professor') {
    $codigo=strtoupper(bin2hex(random_bytes(8)));
    $hash=password_hash($codigo,PASSWORD_DEFAULT);
    $descricao='Emitido pela coordenação #'.$usuario['id_usuario'];
    $s=$conn->prepare('INSERT INTO convite_professor (codigo_hash,descricao,expira_em,usos_maximos) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 7 DAY),1)');
    $s->bind_param('ss',$hash,$descricao); $s->execute();
    responderJson(['status'=>'sucesso','codigo'=>$codigo,'mensagem'=>'Válido por 7 dias para um cadastro de professor.']);
}
responderErro('Ação inválida.');
