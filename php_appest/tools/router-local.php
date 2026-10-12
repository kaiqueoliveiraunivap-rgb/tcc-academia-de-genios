<?php
// O servidor local serve SOMENTE endpoints PHP da raiz da API.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/php_appest/coordenacao/(index\.php|api\.php|painel\.css|painel\.js)?$~D',$path,$rota)) {
    $arquivo=$rota[1] ?? 'index.php';
    $alvo=dirname(__DIR__).'/coordenacao/'.$arquivo;
    if (str_ends_with($arquivo,'.php')) { require $alvo; }
    else { header('Content-Type: '.(str_ends_with($arquivo,'.css') ? 'text/css' : 'application/javascript').'; charset=utf-8'); header('X-Content-Type-Options: nosniff'); readfile($alvo); }
    exit;
}
if ($path==='/php_appest/coordenacao') { header('Location: /php_appest/coordenacao/'); exit; }
if (!preg_match('~^/php_appest/([a-z_]+\.php)$~',$path,$m)) { http_response_code(404); exit; }
$bloqueados=['config.php','conexao.php','auth.php','perfis.php','dashboard_config.php','dashboard_util.php','estudo_validacao.php'];
if (in_array($m[1],$bloqueados,true) || strpos($m[1],'seed_')===0 || $m[1]==='criar_convite_professor.php') { http_response_code(404); exit; }
$alvo=dirname(__DIR__).'/'.$m[1];
if (!is_file($alvo)) { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require $alvo;
