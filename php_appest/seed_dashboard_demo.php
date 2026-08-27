<?php
// Popula o dashboard com dados de demonstração e cria a conta de professor
// usada nos testes.
//
// Como rodar (uma vez):
//     php seed_dashboard_demo.php
//
// Roda SÓ pela linha de comando. Antes também respondia por HTTP, e como ele
// cria uma conta de professor com senha fixa e ainda imprime o login e a senha
// na resposta, bastava abrir a URL num servidor publicado para receber um
// acesso pronto ao dashboard de notas da escola.
//
// É idempotente: rodar de novo não duplica nada. Só insere o que ainda não
// existe e reaproveita os registros já criados (procurando por e-mail e por
// nome da turma), então também não mexe em dados que já estavam no banco.
//
// Os dados aqui são de demonstração e vivem nas MESMAS tabelas que o sistema
// usa em produção (avaliacao/nota). Quando as notas reais começarem a ser
// lançadas, o dashboard passa a mostrá-las sem nenhuma alteração de código —
// basta não rodar este script.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'conexao.php';

// Notas geradas de forma determinística: o mesmo seed produz sempre os mesmos
// números, então um teste feito hoje pode ser repetido amanhã.
mt_srand(20260815);

const SENHA_PROFESSOR_TESTE = "Professor@2026";
const ANO_LETIVO            = "2026";

$conn->begin_transaction();

try {
    // ── Usuários ────────────────────────────────────────────────────────────
    $idProfessor = garantirUsuario($conn, "Marina Alves",  "prof.teste@appest.com",  SENHA_PROFESSOR_TESTE, "professor");
    // Segundo professor: existe para provar, nos testes, que um professor não
    // enxerga nem edita a turma do outro.
    $idOutroProf = garantirUsuario($conn, "Rogério Campos", "prof.outro@appest.com", "Professor@2026", "professor");

    // ── Matérias ────────────────────────────────────────────────────────────
    $materias = [];
    foreach (["Matemática", "Português", "Ciências"] as $nomeMateria) {
        $materias[$nomeMateria] = garantirMateria($conn, $nomeMateria);
    }
    $idsMaterias = array_values($materias);

    // ── Turmas do professor de teste ────────────────────────────────────────
    // "media_alvo" é só o centro em torno do qual as notas são sorteadas, para
    // que cada turma tenha um perfil diferente no gráfico comparativo.
    $turmasDemo = [
        ["nome" => "1º A", "media_alvo" => 8.7, "alunos" => 8],
        ["nome" => "1º B", "media_alvo" => 7.9, "alunos" => 7],
        ["nome" => "2º A", "media_alvo" => 8.4, "alunos" => 9],
        ["nome" => "2º B", "media_alvo" => 6.8, "alunos" => 8],
    ];

    $primeirosNomes = ["Ana", "Bruno", "Carla", "Diego", "Elisa", "Felipe", "Gabriela", "Henrique",
                       "Isabela", "João", "Larissa", "Mateus", "Natália", "Otávio", "Paula", "Rafael"];
    $sobrenomes     = ["Souza", "Lima", "Costa", "Ribeiro", "Martins", "Almeida", "Barbosa", "Teixeira"];

    $totalNotas = 0;

    foreach ($turmasDemo as $indiceTurma => $turmaDemo) {
        $idTurma = garantirTurma($conn, $turmaDemo['nome'], ANO_LETIVO, $idProfessor);

        // Alunos da turma
        $idsAlunos = [];
        for ($i = 0; $i < $turmaDemo['alunos']; $i++) {
            $nome  = $primeirosNomes[($indiceTurma * 4 + $i) % count($primeirosNomes)] . " " .
                     $sobrenomes[($indiceTurma + $i) % count($sobrenomes)];
            $email = "aluno" . ($indiceTurma + 1) . ($i + 1) . "@appest.com";

            $idAluno = garantirUsuario($conn, $nome, $email, "Aluno@2026", "aluno");
            garantirMatricula($conn, $idAluno, $idTurma);
            $idsAlunos[] = $idAluno;
        }

        // 6 avaliações, de março a agosto, alternando as matérias
        for ($mes = 3; $mes <= 8; $mes++) {
            $idMateria = $idsMaterias[($mes - 3) % count($idsMaterias)];
            $titulo    = "Prova " . ($mes - 2) . " · " . array_search($idMateria, $materias, true);
            $data      = sprintf("%s-%02d-15", ANO_LETIVO, $mes);

            $idAvaliacao = garantirAvaliacao($conn, $idTurma, $idMateria, $titulo, $data);

            foreach ($idsAlunos as $posicao => $idAluno) {
                // Perfil do aluno (constante ao longo do ano) + evolução do mês
                $perfilAluno  = (($posicao % 5) - 2) * 0.7;   // -1.4 .. +1.4
                $evolucaoMes  = ($mes - 3) * 0.18;            // turma melhora aos poucos
                $variacao     = mt_rand(-90, 90) / 100.0;

                $nota = $turmaDemo['media_alvo'] + $perfilAluno + $evolucaoMes + $variacao;
                $nota = max(0.0, min(10.0, round($nota, 1)));

                if (garantirNota($conn, $idAvaliacao, $idAluno, $nota)) {
                    $totalNotas++;
                }
            }
        }
    }

    // ── Turma do outro professor (usada só nos testes de isolamento) ────────
    $idTurmaOutro = garantirTurma($conn, "3º C", ANO_LETIVO, $idOutroProf);
    $idAlunoOutro = garantirUsuario($conn, "Vitor Nunes", "aluno.outro@appest.com", "Aluno@2026", "aluno");
    garantirMatricula($conn, $idAlunoOutro, $idTurmaOutro);
    $idAvOutro = garantirAvaliacao($conn, $idTurmaOutro, $idsMaterias[0], "Prova 1 · Matemática", ANO_LETIVO . "-03-15");
    garantirNota($conn, $idAvOutro, $idAlunoOutro, 7.5);

    $conn->commit();

    responder([
        "status"              => "sucesso",
        "id_professor_teste"  => $idProfessor,
        "login"               => "prof.teste@appest.com",
        "senha"               => SENHA_PROFESSOR_TESTE,
        "turmas_criadas"      => count($turmasDemo),
        "notas_inseridas"     => $totalNotas,
        "professor_isolamento"=> ["login" => "prof.outro@appest.com", "id" => $idOutroProf]
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    responder(["status" => "erro", "mensagem" => $e->getMessage()]);
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/** Cria o usuário se o e-mail ainda não existir; devolve sempre o id. */
function garantirUsuario($conn, $nome, $email, $senha, $tipoPerfil)
{
    $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existente = $result->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return intval($existente['id_usuario']);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuario (nome, email, senha, tipo_perfil) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $email, $hash, $tipoPerfil);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function garantirMateria($conn, $nome)
{
    $stmt = $conn->prepare("SELECT id_materia FROM materia WHERE nome = ? LIMIT 1");
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return intval($existente['id_materia']);
    }

    $stmt = $conn->prepare("INSERT INTO materia (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function garantirTurma($conn, $nomeTurma, $anoLetivo, $idProfessor)
{
    $stmt = $conn->prepare(
        "SELECT id_turma FROM turma WHERE nome_turma = ? AND ano_letivo = ? AND id_professor = ? LIMIT 1"
    );
    $stmt->bind_param("ssi", $nomeTurma, $anoLetivo, $idProfessor);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return intval($existente['id_turma']);
    }

    $stmt = $conn->prepare("INSERT INTO turma (nome_turma, ano_letivo, id_professor) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nomeTurma, $anoLetivo, $idProfessor);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function garantirMatricula($conn, $idAluno, $idTurma)
{
    $stmt = $conn->prepare("SELECT id_matricula FROM matricula WHERE id_aluno = ? AND id_turma = ? LIMIT 1");
    $stmt->bind_param("ii", $idAluno, $idTurma);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return intval($existente['id_matricula']);
    }

    $stmt = $conn->prepare("INSERT INTO matricula (id_aluno, id_turma) VALUES (?, ?)");
    $stmt->bind_param("ii", $idAluno, $idTurma);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function garantirAvaliacao($conn, $idTurma, $idMateria, $titulo, $data)
{
    $stmt = $conn->prepare(
        "SELECT id_avaliacao FROM avaliacao WHERE id_turma = ? AND titulo = ? AND data_aplicacao = ? LIMIT 1"
    );
    $stmt->bind_param("iss", $idTurma, $titulo, $data);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return intval($existente['id_avaliacao']);
    }

    $stmt = $conn->prepare(
        "INSERT INTO avaliacao (id_turma, id_materia, titulo, data_aplicacao) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $idTurma, $idMateria, $titulo, $data);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

/** Insere a nota se ainda não houver lançamento desse aluno na avaliação.
 *  Devolve true quando realmente inseriu (para o contador do relatório). */
function garantirNota($conn, $idAvaliacao, $idAluno, $valor)
{
    $stmt = $conn->prepare("SELECT id_nota FROM nota WHERE id_avaliacao = ? AND id_aluno = ? LIMIT 1");
    $stmt->bind_param("ii", $idAvaliacao, $idAluno);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO nota (id_avaliacao, id_aluno, valor) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $idAvaliacao, $idAluno, $valor);
    $stmt->execute();
    $stmt->close();

    return true;
}

function responder($dados)
{
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
