<?php
// Encerra a sessão do app invalidando o token no banco. A partir daí qualquer
// chamada com esse token recebe 401, mesmo que o app ainda o tenha salvo.
require_once 'auth.php';

exigirPost();

$token = $_POST['token'] ?? '';
encerrarSessao($conn, $token);

$conn->close();
responderJson(["status" => "sucesso"]);
