<?php
// Login. Além dos dados do usuário, passa a devolver um token de sessão que o
// app guarda e reenvia nas chamadas do dashboard do professor. Os campos que
// já existiam na resposta foram mantidos para não quebrar as telas antigas.
require_once 'auth.php';

// Só POST: impede que a senha chegue por querystring e acabe registrada no log
// de acesso do servidor.
exigirPost();

if (isset($_POST['email']) && isset($_POST['senha'])) {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $sql = "SELECT u.id_usuario, u.nome, u.email, u.senha, u.tipo_perfil, m.id_turma
            FROM usuario u
            LEFT JOIN matricula m ON u.id_usuario = m.id_aluno
            WHERE u.email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($senha, $user['senha'])) {
            $id_turma = $user['id_turma'] ? intval($user['id_turma']) : 0;
            $token    = criarSessao($conn, intval($user['id_usuario']));

            echo json_encode([
                "status"      => "sucesso",
                "id_usuario"  => intval($user['id_usuario']),
                "nome"        => $user['nome'],
                "email"       => $user['email'],
                "tipo_perfil" => $user['tipo_perfil'],
                "id_turma"    => $id_turma,
                "token"       => $token
            ]);
        } else {
            // Mensagem única para senha errada e usuário inexistente: não
            // entrega para quem tenta adivinhar quais e-mails estão cadastrados.
            echo json_encode(["status" => "erro", "mensagem" => "E-mail ou senha inválidos"]);
        }
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "E-mail ou senha inválidos"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
}
$conn->close();
?>
