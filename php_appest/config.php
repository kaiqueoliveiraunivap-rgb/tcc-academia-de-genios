<?php
// Configuração privada fora da pasta pública. Ambiente tem prioridade.
$arquivoConfig = dirname(__DIR__) . '/config.local.php';
$configLocal = is_file($arquivoConfig) ? require $arquivoConfig : [];
function configuracao($nome, $padrao = '') {
    global $configLocal;
    $valor = getenv($nome);
    if ($valor !== false && $valor !== '') return $valor;

    // O MySQL gerenciado da Railway fornece estes nomes automaticamente.
    // DB_* continua tendo prioridade e mantém compatibilidade com XAMPP/outros hosts.
    $aliasesRailway = [
        'DB_HOST' => 'MYSQLHOST',
        'DB_PORT' => 'MYSQLPORT',
        'DB_NAME' => 'MYSQLDATABASE',
        'DB_USER' => 'MYSQLUSER',
        'DB_PASSWORD' => 'MYSQLPASSWORD',
    ];
    if (isset($aliasesRailway[$nome])) {
        $railway = getenv($aliasesRailway[$nome]);
        if ($railway !== false && $railway !== '') return $railway;
    }
    return $configLocal[$nome] ?? $padrao;
}
