<?php
// Gera um código de convite para cadastro de professor.
//
// Roda SÓ pela linha de comando (a coordenação da escola, no servidor). Se
// fosse acessível por HTTP, qualquer um emitiria o próprio convite e o
// controle do cadastro de professor deixaria de existir.
//
//   php criar_convite_professor.php "Professores 2026" 5 30
//
//   arg 1  descrição (para saber depois de onde veio o convite)
//   arg 2  quantos cadastros o código aceita   (padrão: 1)
//   arg 3  em quantos dias o código expira     (padrão: 7; 0 = sem prazo)
//
// O código aparece UMA única vez, aqui na saída: no banco fica só o hash.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'conexao.php';

$descricao    = $argv[1] ?? 'Convite de professor';
$usosMaximos  = isset($argv[2]) ? max(1, intval($argv[2])) : 1;
$dias         = isset($argv[3]) ? max(0, intval($argv[3])) : 7;

// 8 bytes viram 16 caracteres hexadecimais em maiúsculas: curto o bastante
// para o professor digitar, longo o bastante para não ser adivinhado.
$codigo = strtoupper(bin2hex(random_bytes(8)));
$hash   = password_hash($codigo, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO convite_professor (codigo_hash, descricao, expira_em, usos_maximos)
     VALUES (?, ?, " . ($dias > 0 ? "DATE_ADD(NOW(), INTERVAL ? DAY)" : "NULL") . ", ?)"
);

if ($dias > 0) {
    $stmt->bind_param("ssii", $hash, $descricao, $dias, $usosMaximos);
} else {
    $stmt->bind_param("ssi", $hash, $descricao, $usosMaximos);
}

$stmt->execute();
$idConvite = $stmt->insert_id;
$stmt->close();
$conn->close();

echo "Convite #{$idConvite} criado.\n";
echo "  Descrição : {$descricao}\n";
echo "  Cadastros : {$usosMaximos}\n";
echo "  Validade  : " . ($dias > 0 ? "{$dias} dia(s)" : "sem prazo") . "\n";
echo "\n";
echo "  CÓDIGO: {$codigo}\n";
echo "\n";
echo "Anote agora: o código não pode ser recuperado depois (o banco guarda só o hash).\n";
