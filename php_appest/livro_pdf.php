<?php
// PDF privado: o identificador sozinho não autoriza a leitura.
define('COORD_INTERNO',true);
require __DIR__.'/coordenacao/interno.php';
$arquivo=$_GET['arquivo'] ?? '';
$idUsuario=filter_var($_GET['usuario'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$expira=filter_var($_GET['expira'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$assinatura=$_GET['assinatura'] ?? '';
if (!is_string($arquivo) || !preg_match('/^[a-f0-9]{64}$/D',$arquivo) ||
    !$idUsuario || !$expira || $expira<time() || $expira>time()+600 ||
    !is_string($assinatura) || !preg_match('/^[a-f0-9]{64}$/D',$assinatura)) {
    http_response_code(403); exit;
}
$livro=linhas("SELECT l.id_livro FROM coordenacao_livro j JOIN livro_didatico l ON l.id_livro=j.id_livro
    WHERE j.arquivo=? AND j.estado='pronto' AND l.ativo=1 LIMIT 1",[$arquivo])[0] ?? null;
if (!$livro) { http_response_code(404); exit; }
$acesso=linhas("SELECT 1 FROM capitulo_livro c WHERE c.id_livro=? AND c.revisado=1 AND (
    NOT EXISTS (SELECT 1 FROM conteudo_capitulo cc WHERE cc.id_capitulo=c.id_capitulo)
    OR EXISTS (SELECT 1 FROM conteudo_capitulo cc
        JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo AND cp.ativo=1
        JOIN turma t ON t.serie=cp.serie JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
        WHERE cc.id_capitulo=c.id_capitulo)) LIMIT 1",[$livro['id_livro'],$idUsuario]);
if (!$acesso) { http_response_code(403); exit; }
$sessoes=linhas("SELECT s.token FROM sessao s JOIN usuario u ON u.id_usuario=s.id_usuario
    WHERE s.id_usuario=? AND s.ativo=1 AND s.expira_em>NOW() AND u.excluido_em IS NULL AND LOWER(u.tipo_perfil)='aluno'",[$idUsuario]);
$autorizado=false;
foreach ($sessoes as $sessao) {
    if (hash_equals(hash_hmac('sha256',$arquivo.'|'.$idUsuario.'|'.$expira,$sessao['token']),$assinatura)) {
        $autorizado=true; break;
    }
}
if (!$autorizado) { http_response_code(403); exit; }
$path=pastaLivros().'/'.$arquivo.'.pdf';
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="livro.pdf"');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
readfile($path);
