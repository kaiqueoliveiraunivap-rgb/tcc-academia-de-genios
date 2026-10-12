<?php
// Autenticação e autorização dos endpoints do professor.
//
// Regra do projeto: o app NUNCA é fonte de verdade sobre quem é o usuário.
// O app envia apenas o token recebido no login; quem é o usuário, qual o
// perfil dele e de quais turmas ele é dono é sempre resolvido aqui, no
// servidor, consultando o banco. Trocar um id na requisição não muda nada.

require_once 'conexao.php';
require_once 'dashboard_config.php';

/** Envia a resposta JSON e encerra a requisição. */
function responderJson($dados, $httpStatus = 200)
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Resposta padrão de erro. A mensagem é genérica de propósito: não conta ao
 *  cliente se o recurso existe, apenas que ele não pode acessá-lo. */
function responderErro($mensagem, $httpStatus = 400)
{
    responderJson(["status" => "erro", "mensagem" => $mensagem], $httpStatus);
}

/**
 * Lê um inteiro do corpo da requisição, já validando a faixa.
 * Devolve null quando o campo falta ou não é um inteiro aceitável.
 *
 * Lê de $_POST, e não por filter_input(INPUT_POST, ...): filter_input consulta
 * a cópia que o PHP montou no início da requisição, que não existe fora de um
 * acesso HTTP real. Manter tudo em $_POST deixa os endpoints testáveis pela
 * linha de comando e alinhados com o resto do projeto, que já lê $_POST.
 */
function inteiroDoPost($campo, $min = 1, $max = PHP_INT_MAX)
{
    $valor = filter_var($_POST[$campo] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    return $valor === false ? null : $valor;
}

/** Bloqueia qualquer verbo diferente de POST (evita chamada acidental por URL). */
function exigirPost()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        responderErro("Método não permitido.", 405);
    }
}

/** Token opaco de 64 caracteres, gerado por fonte criptográfica. */
function gerarToken()
{
    return bin2hex(random_bytes(32));
}

/** Cria uma sessão para o usuário e devolve o token gerado. */
function criarSessao($conn, $idUsuario)
{
    $token = gerarToken();
    $horas = SESSAO_HORAS;

    $stmt = $conn->prepare(
        "INSERT INTO sessao (id_usuario, token, expira_em)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))"
    );
    $stmt->bind_param("isi", $idUsuario, $token, $horas);
    $stmt->execute();
    $stmt->close();

    return $token;
}

/** Invalida um token específico (logout). Sempre responde sucesso para não
 *  revelar se o token existia. */
function encerrarSessao($conn, $token)
{
    $stmt = $conn->prepare("UPDATE sessao SET ativo = 0 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
}

/** Devolve o usuário dono do token, ou null se o token for inválido/expirado. */
function usuarioDaSessao($conn, $token)
{
    if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome, u.email, u.tipo_perfil
         FROM sessao s
         JOIN usuario u ON u.id_usuario = s.id_usuario
         WHERE s.token = ? AND s.ativo = 1 AND s.expira_em > NOW() AND u.excluido_em IS NULL
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($usuario !== null) {
        $usuario['id_usuario'] = intval($usuario['id_usuario']);
    }
    return $usuario;
}

/**
 * Exige POST e token válido, de qualquer perfil. Usado nos endpoints em que
 * o aluno age sobre os próprios dados: o id vem da sessão, nunca do corpo da
 * requisição, então trocar um id na chamada não dá acesso a outro usuário.
 */
function exigirUsuarioLogado($conn)
{
    exigirPost();

    $usuario = usuarioDaSessao($conn, $_POST['token'] ?? '');

    if ($usuario === null) {
        responderErro("Sessão inválida ou expirada. Faça login novamente.", 401);
    }
    return $usuario;
}

/**
 * Porta de entrada de todo endpoint do dashboard: exige POST, token válido e
 * perfil de professor. Encerra a requisição em caso de falha.
 * Devolve o registro do professor autenticado.
 */
function exigirProfessor($conn)
{
    exigirPost();

    $usuario = usuarioDaSessao($conn, $_POST['token'] ?? '');

    if ($usuario === null) {
        responderErro("Sessão inválida ou expirada. Faça login novamente.", 401);
    }
    if (strtolower($usuario['tipo_perfil']) !== 'professor') {
        responderErro("Acesso permitido apenas a professores.", 403);
    }
    return $usuario;
}

/**
 * Confirma no banco que a turma pertence ao professor autenticado.
 * É esta função que impede o professor de trocar o id_turma na requisição
 * para ver os dados da turma de outro professor.
 */
function exigirTurmaDoProfessor($conn, $idTurma, $idProfessor)
{
    $stmt = $conn->prepare(
        "SELECT id_turma, nome_turma, ano_letivo
         FROM turma
         WHERE id_turma = ? AND id_professor = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $idTurma, $idProfessor);
    $stmt->execute();
    $result = $stmt->get_result();
    $turma = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($turma === null) {
        responderErro("Turma não encontrada para este professor.", 403);
    }

    $turma['id_turma'] = intval($turma['id_turma']);
    return $turma;
}

/**
 * Confirma que a avaliação pertence a uma turma do professor autenticado.
 * Usado antes de qualquer edição de nota.
 */
function exigirAvaliacaoDoProfessor($conn, $idAvaliacao, $idProfessor)
{
    $stmt = $conn->prepare(
        "SELECT a.id_avaliacao, a.id_turma, a.titulo
         FROM avaliacao a
         JOIN turma t ON t.id_turma = a.id_turma
         WHERE a.id_avaliacao = ? AND t.id_professor = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $idAvaliacao, $idProfessor);
    $stmt->execute();
    $result = $stmt->get_result();
    $avaliacao = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($avaliacao === null) {
        responderErro("Avaliação não encontrada para este professor.", 403);
    }

    $avaliacao['id_avaliacao'] = intval($avaliacao['id_avaliacao']);
    $avaliacao['id_turma']     = intval($avaliacao['id_turma']);
    return $avaliacao;
}

/**
 * Confirma que o aluno está matriculado na turma informada.
 * Impede o professor de agir sobre um aluno que não é dele apenas trocando o
 * id_aluno na requisição (a turma já foi validada como dele antes disto).
 */
function exigirAlunoNaTurma($conn, $idAluno, $idTurma)
{
    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome
         FROM matricula m
         JOIN usuario u ON u.id_usuario = m.id_aluno
         WHERE m.id_aluno = ? AND m.id_turma = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $idAluno, $idTurma);
    $stmt->execute();
    $result = $stmt->get_result();
    $aluno = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($aluno === null) {
        responderErro("Aluno não encontrado nesta turma.", 403);
    }

    $aluno['id_usuario'] = intval($aluno['id_usuario']);
    return $aluno;
}

/**
 * Procura um convite de professor válido que case com o código informado.
 * Devolve o convite ou null.
 *
 * Os códigos ficam hasheados no banco, então não dá para buscar por igualdade:
 * é preciso percorrer os convites ainda válidos e testar um a um. A lista é
 * curta por natureza (convites são emitidos pela coordenação, não em massa).
 */
function conviteProfessorValido($conn, $codigo)
{
    if (!is_string($codigo) || $codigo === '') {
        return null;
    }

    $result = $conn->query(
        "SELECT id_convite, codigo_hash, usos, usos_maximos
         FROM convite_professor
         WHERE ativo = 1
           AND usos < usos_maximos
           AND (expira_em IS NULL OR expira_em > NOW())"
    );

    while ($convite = $result->fetch_assoc()) {
        if (password_verify($codigo, $convite['codigo_hash'])) {
            $result->free();
            $convite['id_convite'] = intval($convite['id_convite']);
            return $convite;
        }
    }
    $result->free();
    return null;
}

/**
 * Marca mais um uso do convite e registra quem o usou.
 *
 * O UPDATE repete a condição "usos < usos_maximos" de propósito: se duas
 * pessoas enviarem o mesmo código ao mesmo tempo, apenas uma passa da última
 * vaga — quem chegar depois altera 0 linhas e é recusado.
 */
function consumirConviteProfessor($conn, $idConvite, $idUsuario)
{
    $stmt = $conn->prepare(
        "UPDATE convite_professor
         SET usos = usos + 1
         WHERE id_convite = ? AND usos < usos_maximos AND ativo = 1"
    );
    $stmt->bind_param("i", $idConvite);
    $stmt->execute();
    $consumiu = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$consumiu) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO convite_professor_uso (id_convite, id_usuario) VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $idConvite, $idUsuario);
    $stmt->execute();
    $stmt->close();

    return true;
}
