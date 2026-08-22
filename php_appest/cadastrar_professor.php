<?php
// Cadastro de professor.
//
// Regra: o perfil "professor" dá acesso às notas da turma e ao envio de
// orientações para a IA, então ele nunca é auto-atribuído. Só cria conta quem
// apresenta um código de convite válido, emitido pela coordenação com
// criar_convite_professor.php.
//
// Tudo que decide o resultado é resolvido aqui no servidor: o app não escolhe
// o tipo_perfil, não informa id e não é acreditado em nenhuma validação.

require_once 'auth.php';

exigirPost();

const SENHA_MINIMA = 8;

$nome   = trim($_POST['nome']   ?? '');
$email  = trim($_POST['email']  ?? '');
$senha  = $_POST['senha']       ?? '';
$codigo = trim($_POST['codigo_convite'] ?? '');

// ── Validação de entrada ────────────────────────────────────────────────────
if ($nome === '' || $email === '' || $senha === '' || $codigo === '') {
    responderErro("Preencha nome, e-mail, senha e código de convite.", 422);
}

if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) {
    responderErro("O nome deve ter entre 3 e 120 caracteres.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    responderErro("Informe um e-mail válido.", 422);
}

if (mb_strlen($senha) < SENHA_MINIMA) {
    responderErro("A senha deve ter no mínimo " . SENHA_MINIMA . " caracteres.", 422);
}

// Exige variedade mínima para a senha não ser só uma palavra do dicionário.
if (!preg_match('/[A-Za-zÀ-ÿ]/u', $senha) || !preg_match('/\d/', $senha)) {
    responderErro("A senha deve conter letras e números.", 422);
}

// ── Convite ─────────────────────────────────────────────────────────────────
// A mensagem é a mesma para código inexistente, expirado ou esgotado: não
// entrega a quem está tentando adivinhar qual das hipóteses é a certa.
$convite = conviteProfessorValido($conn, $codigo);
if ($convite === null) {
    responderErro("Código de convite inválido ou já utilizado.", 403);
}

// ── Criação da conta ────────────────────────────────────────────────────────
// A transação garante que não sobre um professor cadastrado sem o convite ter
// sido consumido (nem o contrário) caso alguma etapa falhe no meio.
$conn->begin_transaction();

try {
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO usuario (nome, email, senha, tipo_perfil)
         VALUES (?, ?, ?, 'professor')"
    );
    $stmt->bind_param("sss", $nome, $email, $senhaHash);
    $stmt->execute();
    $idUsuario = $stmt->insert_id;
    $stmt->close();

    if (!consumirConviteProfessor($conn, $convite['id_convite'], $idUsuario)) {
        // Outra pessoa pegou a última vaga entre a checagem e o consumo.
        $conn->rollback();
        responderErro("Código de convite inválido ou já utilizado.", 403);
    }

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();

    // 1062 = violação de UNIQUE. É o único caso que vale contar ao usuário,
    // porque ele precisa saber que deve usar outro e-mail.
    if ($e->getCode() === 1062) {
        responderErro("Já existe uma conta com este e-mail.", 409);
    }

    // Qualquer outra falha vai para o log do servidor: a mensagem do banco
    // pode revelar nomes de tabelas e caminhos de arquivo.
    error_log("Falha ao cadastrar professor: " . $e->getMessage());
    responderErro("Não foi possível concluir o cadastro. Tente novamente.", 500);
}

// A conta é criada, mas o login continua sendo um passo separado: o cadastro
// não devolve token nem loga automaticamente.
responderJson([
    "status"     => "sucesso",
    "id_usuario" => intval($idUsuario),
    "mensagem"   => "Cadastro concluído. Faça login para entrar."
], 201);
