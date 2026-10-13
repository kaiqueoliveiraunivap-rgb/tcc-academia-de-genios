<?php
// O link do PDF privado vale poucos minutos e nunca contém o token de login.
function urlPdfParaAluno(mysqli $conn,string $url,int $idAluno,string $token): string {
    if (!preg_match('~^/php_appest/livro_pdf\.php\?arquivo=([a-f0-9]{64})$~D',$url,$m)) return $url;
    $s=$conn->prepare('SELECT 1 FROM sessao WHERE id_usuario=? AND token=? AND ativo=1 AND expira_em>NOW() LIMIT 1');
    $s->bind_param('is',$idAluno,$token); $s->execute();
    $valida=$s->get_result()->num_rows===1; $s->close();
    if (!$valida) return '';
    $expira=time()+600;
    $assinatura=hash_hmac('sha256',$m[1].'|'.$idAluno.'|'.$expira,$token);
    return $url.'&usuario='.$idAluno.'&expira='.$expira.'&assinatura='.$assinatura;
}
