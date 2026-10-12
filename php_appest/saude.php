<?php
require_once __DIR__.'/conexao.php';
header('Content-Type: application/json; charset=utf-8');
$limite=ini_get('upload_max_filesize');
$bytes=(int)$limite * match(strtolower(substr($limite,-1))) { 'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1 };
echo json_encode(['status'=>'sucesso','servico'=>'academia-genios-api','versao'=>2,'upload_max_bytes'=>$bytes,'erros_publicos'=>(bool)ini_get('display_errors')]);
