<?php
$servidor = "localhost";
$usuario  = "root";
$senha    = "";
$banco    = "appest";

// No PHP 8 o mysqli lança exceção quando a conexão falha, então o antigo teste
// de $conn->connect_error nunca era alcançado: a exceção subia e o PHP imprimia
// o stack trace com caminho dos arquivos e credenciais na resposta HTTP.
// Aqui a falha é capturada e devolvida como JSON genérico; o detalhe real vai
// para o log do servidor, onde só o administrador vê.
try {
    $conn = new mysqli($servidor, $usuario, $senha, $banco);
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
