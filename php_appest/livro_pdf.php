<?php
// Link de leitura com identificador aleatório de 256 bits, entregue junto ao estudo.
define('COORD_INTERNO',true);
require __DIR__.'/coordenacao/interno.php';
$arquivo=$_GET['arquivo'] ?? '';
if (!is_string($arquivo) || !preg_match('/^[a-f0-9]{64}$/D',$arquivo) || !linhas("SELECT j.id FROM coordenacao_livro j JOIN livro_didatico l ON l.id_livro=j.id_livro WHERE j.arquivo=? AND j.estado='pronto' AND l.ativo=1",[$arquivo])) { http_response_code(404); exit; }
$path=pastaLivros().'/'.$arquivo.'.pdf';
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="livro.pdf"');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
readfile($path);
