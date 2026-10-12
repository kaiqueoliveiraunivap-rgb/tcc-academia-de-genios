<?php
// Login. Além dos dados do usuário, passa a devolver um token de sessão que o
// app guarda e reenvia nas chamadas do dashboard do professor. Os campos que
// já existiam na resposta foram mantidos para não quebrar as telas antigas.
require_once 'auth.php';
require_once __DIR__ . '/perfis.php';

// Só POST: impede que a senha chegue por querystring e acabe registrada no log
// de acesso do servidor.
exigirPost();
$perfil = normalizarPerfil($_POST['tipo_perfil'] ?? null);
if ($perfil === null) responderErro('Selecione Aluno, Professor ou Coordenação.', 422);
// A coordenação entra exclusivamente pelo painel web, com limitação de tentativas.
if ($perfil === 'admin') responderErro('Acesse o painel web da coordenação.',403);

if (isset($_POST['email']) && isset($_POST['senha'])) {
    if (!is_string($_POST['email']) || !is_string($_POST['senha'])) responderErro('Dados inválidos.',422);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    $sql = "SELECT u.id_usuario, u.nome, u.email, u.senha, u.tipo_perfil,
                   (SELECT MIN(m.id_turma) FROM matricula m WHERE m.id_aluno=u.id_usuario) AS id_turma
            FROM usuario u
            WHERE u.email = ? AND u.excluido_em IS NULL LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (normalizarPerfil($user['tipo_perfil']) === $perfil && password_verify($senha, $user['senha'])) {
            $id_turma = $user['id_turma'] ? intval($user['id_turma']) : 0;
            $token    = criarSessao($conn, intval($user['id_usuario']));

            echo json_encode([
                "status"      => "sucesso",
                "id_usuario"  => intval($user['id_usuario']),
                "nome"        => $user['nome'],
                "email"       => $user['email'],
                "tipo_perfil" => $perfil,
                "id_turma"    => $id_turma,
                "token"       => $token
            ]);
        } else {
            // Mensagem única para senha errada e usuário inexistente: não
            // entrega para quem tenta adivinhar quais e-mails estão cadastrados.
            responderErro('E-mail, senha ou perfil incorretos.', 401);
        }
    } else {
        responderErro('E-mail, senha ou perfil incorretos.', 401);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>
