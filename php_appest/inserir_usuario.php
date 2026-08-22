<?php
// Cadastro de aluno.
//
// Este endpoint só cria conta de ALUNO. Antes ele aceitava o tipo_perfil vindo
// do cliente, então bastava enviar "professor" (ou "admin") para ganhar acesso
// ao dashboard de notas da escola. Perfil de professor agora se obtém apenas
// por cadastrar_professor.php, que exige código de convite.
require_once 'auth.php';

exigirPost();

const SENHA_MINIMA_ALUNO = 8;

$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha']      ?? '';

if ($nome === '' || $email === '' || $senha === '') {
    responderErro("Preencha nome, e-mail e senha.", 422);
}

if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) {
    responderErro("O nome deve ter entre 3 e 120 caracteres.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    responderErro("Informe um e-mail válido.", 422);
}

if (mb_strlen($senha) < SENHA_MINIMA_ALUNO) {
    responderErro("A senha deve ter no mínimo " . SENHA_MINIMA_ALUNO . " caracteres.", 422);
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO usuario (nome, email, senha, tipo_perfil) VALUES (?, ?, ?, 'aluno')"
);
$stmt->bind_param("sss", $nome, $email, $senhaHash);

try {
    $stmt->execute();
    $idUsuario = $stmt->insert_id;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        responderErro("Já existe uma conta com este e-mail.", 409);
    }
    error_log("Falha ao cadastrar aluno: " . $e->getMessage());
    responderErro("Não foi possível concluir o cadastro. Tente novamente.", 500);
}
$stmt->close();
$conn->close();

responderJson(["status" => "sucesso", "id_usuario" => intval($idUsuario)], 201);
