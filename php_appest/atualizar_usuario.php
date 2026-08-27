<?php
// Atualiza nome e e-mail da PRÓPRIA conta.
//
// Antes este era o endpoint mais perigoso do backend: sem login nenhum, ele
// aceitava id_usuario e tipo_perfil do cliente. Bastava uma requisição para
// promover a si mesmo a professor, ou rebaixar/renomear qualquer outra conta.
//
// Duas mudanças fecham isso:
//   1. O usuário editado é sempre o dono do token (id_usuario não é mais lido
//      do corpo da requisição).
//   2. tipo_perfil deixou de ser editável aqui. Perfil de professor se obtém
//      só por cadastrar_professor.php, que exige código de convite.

require_once 'auth.php';

$usuario = exigirUsuarioLogado($conn);

$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');

if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) {
    responderErro("O nome deve ter entre 3 e 120 caracteres.", 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    responderErro("Informe um e-mail válido.", 422);
}

$stmt = $conn->prepare("UPDATE usuario SET nome = ?, email = ? WHERE id_usuario = ?");
$stmt->bind_param("ssi", $nome, $email, $usuario['id_usuario']);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    if ($e->getCode() === 1062) {
        responderErro("Já existe uma conta com este e-mail.", 409);
    }
    error_log("atualizar_usuario: " . $e->getMessage());
    responderErro("Não foi possível atualizar o cadastro.", 500);
}
$stmt->close();

responderJson(["status" => "atualizado"]);
