<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
$conn=new mysqli(configuracao('DB_HOST','127.0.0.1'),configuracao('DB_USER','root'),configuracao('DB_PASSWORD'),configuracao('DB_NAME','appest'),(int)configuracao('DB_PORT',3306));
$conn->set_charset('utf8mb4');
require __DIR__.'/migrar_orientacoes.php';
echo "Orientações por livro e bimestre prontas.\n";
