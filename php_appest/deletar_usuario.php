<?php
// Apaga uma conta de usuário e tudo que depende dela.
//
// Roda SÓ pela linha de comando, no servidor — mesma regra de
// criar_convite_professor.php.
//
// Antes era um endpoint HTTP aberto: qualquer requisição com um id_usuario
// apagava a conta correspondente, inclusive a de um professor. Apagar conta é
// destrutivo e irreversível, e o schema não tem perfil de administrador para
// autorizar isso por token — então a operação sai do alcance da rede em vez de
// ficar protegida por uma permissão que não existe.
//
//   php deletar_usuario.php <id_usuario>

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'conexao.php';

$idUsuario = isset($argv[1]) ? intval($argv[1]) : 0;

if ($idUsuario <= 0) {
    fwrite(STDERR, "Uso: php deletar_usuario.php <id_usuario>\n");
    exit(1);
}

$stmt = $conn->prepare("SELECT nome, email, tipo_perfil FROM usuario WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario === null) {
    fwrite(STDERR, "Usuário $idUsuario não encontrado.\n");
    exit(1);
}

if (strtolower($usuario['tipo_perfil']) === 'professor') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM turma WHERE id_professor = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $turmas = intval($stmt->get_result()->fetch_assoc()['total']);
    $stmt->close();

    if ($turmas > 0) {
        fwrite(STDERR, "Professor ainda é dono de $turmas turma(s). Transfira ou apague as turmas antes.\n");
        exit(1);
    }
}

echo "Apagar {$usuario['nome']} <{$usuario['email']}> ({$usuario['tipo_perfil']})? [s/N] ";
if (strtolower(trim(fgets(STDIN))) !== 's') {
    echo "Cancelado.\n";
    exit(0);
}

// A conta é referenciada por várias tabelas. Todas as remoções vão na mesma
// transação: se qualquer uma falhar, nada é apagado.
$conn->begin_transaction();

try {
    $dependentes = [
        "DELETE FROM sessao WHERE id_usuario = ?",
        "DELETE FROM ranking WHERE id_aluno = ?",
        "DELETE FROM matricula WHERE id_aluno = ?",
        "DELETE FROM historico WHERE id_aluno = ?",
        "DELETE FROM historico_quiz WHERE id_aluno = ?",
    ];

    foreach ($dependentes as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    fwrite(STDERR, "Falha ao apagar: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Usuário $idUsuario apagado.\n";
