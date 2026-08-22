<?php
// Tela 1 do dashboard — visão geral das turmas do professor autenticado.
//
// Só retorna turmas em que turma.id_professor = professor do token. Não existe
// parâmetro de id_professor na requisição: mesmo que o app mandasse um, ele
// seria ignorado.
//
// São 3 consultas no total, independente da quantidade de turmas (sem N+1):
//   1) turmas + total de alunos
//   2) média de cada aluno em cada turma
//   3) média da turma por mês (evolução)

require_once 'auth.php';
require_once 'dashboard_util.php';

$professor   = exigirProfessor($conn);
$idProfessor = $professor['id_usuario'];

// ── 1) Turmas do professor + quantidade de alunos matriculados ──────────────
$stmt = $conn->prepare(
    "SELECT t.id_turma, t.nome_turma, t.ano_letivo,
            COUNT(DISTINCT m.id_aluno) AS total_alunos
     FROM turma t
     LEFT JOIN matricula m ON m.id_turma = t.id_turma
     WHERE t.id_professor = ?
     GROUP BY t.id_turma, t.nome_turma, t.ano_letivo
     ORDER BY t.ano_letivo DESC, t.nome_turma ASC"
);
$stmt->bind_param("i", $idProfessor);
$stmt->execute();
$result = $stmt->get_result();

$turmas = [];
while ($row = $result->fetch_assoc()) {
    $idTurma = intval($row['id_turma']);
    $turmas[$idTurma] = [
        "id_turma"               => $idTurma,
        "nome_turma"             => $row['nome_turma'],
        "ano_letivo"             => $row['ano_letivo'],
        "total_alunos"           => intval($row['total_alunos']),
        "total_avaliados"        => 0,
        "media_geral"            => 0.0,
        "percentual_aprovacao"   => 0.0,
        "percentual_abaixo"      => 0.0,
        "melhor_aluno"           => null,
        "melhor_media"           => 0.0,
        "menor_aluno"            => null,
        "menor_media"            => 0.0,
        "variacao_media"         => 0.0,
        "evolucao"               => []
    ];
}
$stmt->close();

if (empty($turmas)) {
    $conn->close();
    responderJson(["status" => "sucesso", "turmas" => []]);
}

// ── 2) Média de cada aluno dentro de cada turma ─────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.id_turma, u.id_usuario, u.nome, AVG(n.valor) AS media_aluno
     FROM turma t
     JOIN avaliacao a ON a.id_turma = t.id_turma
     JOIN nota n      ON n.id_avaliacao = a.id_avaliacao
     JOIN usuario u   ON u.id_usuario = n.id_aluno
     JOIN matricula m ON m.id_aluno = n.id_aluno AND m.id_turma = t.id_turma
     WHERE t.id_professor = ?
     GROUP BY a.id_turma, u.id_usuario, u.nome"
);
$stmt->bind_param("i", $idProfessor);
$stmt->execute();
$result = $stmt->get_result();

$mediasPorTurma = [];
while ($row = $result->fetch_assoc()) {
    $mediasPorTurma[intval($row['id_turma'])][] = [
        "nome"  => $row['nome'],
        "media" => floatval($row['media_aluno'])
    ];
}
$stmt->close();

foreach ($mediasPorTurma as $idTurma => $alunos) {
    if (!isset($turmas[$idTurma]) || empty($alunos)) {
        continue;
    }

    $medias    = array_column($alunos, 'media');
    $aprovados = count(array_filter($medias, 'estaAprovado'));
    $total     = count($medias);

    usort($alunos, fn($a, $b) => $b['media'] <=> $a['media']);
    $melhor = $alunos[0];
    $menor  = $alunos[$total - 1];

    $turmas[$idTurma]["total_avaliados"]      = $total;
    $turmas[$idTurma]["media_geral"]          = arredondar(array_sum($medias) / $total);
    $turmas[$idTurma]["percentual_aprovacao"] = percentual($aprovados, $total);
    $turmas[$idTurma]["percentual_abaixo"]    = percentual($total - $aprovados, $total);
    $turmas[$idTurma]["melhor_aluno"]         = $melhor['nome'];
    $turmas[$idTurma]["melhor_media"]         = arredondar($melhor['media']);
    $turmas[$idTurma]["menor_aluno"]          = $menor['nome'];
    $turmas[$idTurma]["menor_media"]          = arredondar($menor['media']);
}

// ── 3) Evolução: média da turma mês a mês ───────────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.id_turma,
            DATE_FORMAT(a.data_aplicacao, '%Y-%m') AS periodo,
            AVG(n.valor) AS media_periodo
     FROM turma t
     JOIN avaliacao a ON a.id_turma = t.id_turma
     JOIN nota n      ON n.id_avaliacao = a.id_avaliacao
     WHERE t.id_professor = ?
     GROUP BY a.id_turma, periodo
     ORDER BY periodo ASC"
);
$stmt->bind_param("i", $idProfessor);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $idTurma = intval($row['id_turma']);
    if (!isset($turmas[$idTurma])) {
        continue;
    }
    $turmas[$idTurma]["evolucao"][] = [
        "periodo" => $row['periodo'],
        "rotulo"  => rotuloMes($row['periodo']),
        "media"   => arredondar($row['media_periodo'])
    ];
}
$stmt->close();

// Variação = quanto a turma subiu ou caiu do primeiro para o último período.
foreach ($turmas as $idTurma => $turma) {
    $evolucao = $turma['evolucao'];
    $qtd      = count($evolucao);
    if ($qtd >= 2) {
        $turmas[$idTurma]["variacao_media"] =
            arredondar($evolucao[$qtd - 1]['media'] - $evolucao[0]['media']);
    }
}

$conn->close();

responderJson([
    "status"          => "sucesso",
    "media_aprovacao" => MEDIA_APROVACAO,
    "turmas"          => array_values($turmas)
]);
