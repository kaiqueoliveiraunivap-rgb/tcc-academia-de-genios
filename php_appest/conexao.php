<?php
require_once __DIR__ . '/config.php';
$servidor = configuracao('DB_HOST', '127.0.0.1');
$usuario  = configuracao('DB_USER', 'root');
$senha    = configuracao('DB_PASSWORD');
$banco    = configuracao('DB_NAME', 'appest');

// No PHP 8 o mysqli lança exceção quando a conexão falha, então o antigo teste
// de $conn->connect_error nunca era alcançado: a exceção subia e o PHP imprimia
// o stack trace com caminho dos arquivos e credenciais na resposta HTTP.
// Aqui a falha é capturada e devolvida como JSON genérico; o detalhe real vai
// para o log do servidor, onde só o administrador vê.
try {
    $conn = new mysqli($servidor, $usuario, $senha, $banco, (int)configuracao('DB_PORT', 3306));
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log("Falha ao conectar no banco {$banco}: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        "status"   => "erro",
        "mensagem" => "Serviço indisponível no momento. Tente novamente."
    ], JSON_UNESCAPED_UNICODE));
}
?>
