<?php
// Tela 2 do dashboard — análise detalhada de UMA turma.
//
// Parâmetros: token (obrigatório), id_turma (obrigatório) e os filtros
// opcionais id_materia, id_avaliacao e periodo (YYYY-MM).
//
// A turma só é aceita depois de exigirTurmaDoProfessor(), que confirma no
// banco que ela pertence ao professor do token. Todas as consultas seguintes
// já ficam presas a essa turma, então nenhum filtro consegue "escapar" para
// dados de outra turma.
//
// Os números da tela (resumo, distribuição, desempenho por avaliação, evolução
// e lista de alunos) são todos derivados do MESMO conjunto de notas filtradas,
// para que os gráficos nunca discordem entre si.

require_once 'auth.php';
require_once 'dashboard_util.php';

$professor   = exigirProfessor($conn);
$idProfessor = $professor['id_usuario'];

$idTurma = intval($_POST['id_turma'] ?? 0);
if ($idTurma <= 0) {
    responderErro("Turma não informada.", 400);
}

$turma = exigirTurmaDoProfessor($conn, $idTurma, $idProfessor);

// ── Filtros ─────────────────────────────────────────────────────────────────
$idMateria   = intval($_POST['id_materia'] ?? 0);
$idAvaliacao = intval($_POST['id_avaliacao'] ?? 0);
$periodo     = trim($_POST['periodo'] ?? '');
if ($periodo !== '' && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = ''; // formato inválido é simplesmente ignorado
}

// ── Total de alunos matriculados na turma ───────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(DISTINCT id_aluno) AS total FROM matricula WHERE id_turma = ?");
$stmt->bind_param("i", $idTurma);
$stmt->execute();
$totalAlunos = intval($stmt->get_result()->fetch_assoc()['total']);
$stmt->close();

// ── Avaliações da turma: alimentam os três seletores da tela ────────────────
// (não sofrem os filtros, senão o professor não conseguiria trocar de filtro)
$stmt = $conn->prepare(
    "SELECT a.id_avaliacao, a.titulo, a.data_aplicacao,
            DATE_FORMAT(a.data_aplicacao, '%Y-%m') AS periodo,
            a.id_materia, m.nome AS materia
     FROM avaliacao a
     LEFT JOIN materia m ON m.id_materia = a.id_materia
     WHERE a.id_turma = ?
     ORDER BY a.data_aplicacao ASC, a.id_avaliacao ASC"
);
$stmt->bind_param("i", $idTurma);
$stmt->execute();
$result = $stmt->get_result();

$avaliacoesDisponiveis = [];
$materiasDisponiveis   = [];
$periodosDisponiveis   = [];

while ($row = $result->fetch_assoc()) {
    $avaliacoesDisponiveis[] = [
        "id_avaliacao" => intval($row['id_avaliacao']),
        "titulo"       => $row['titulo'],
        "data"         => $row['data_aplicacao'],
        "periodo"      => $row['periodo'],
        "materia"      => $row['materia']
    ];

    if ($row['id_materia'] !== null) {
        $materiasDisponiveis[intval($row['id_materia'])] = [
            "id_materia" => intval($row['id_materia']),
            "nome"       => $row['materia']
        ];
    }

    $periodosDisponiveis[$row['periodo']] = [
        "periodo" => $row['periodo'],
        "rotulo"  => rotuloMes($row['periodo'])
    ];
}
$stmt->close();

ksort($periodosDisponiveis);

// ── Notas filtradas ─────────────────────────────────────────────────────────
$sql = "SELECT n.id_nota, n.valor, n.id_aluno, u.nome AS nome_aluno,
               a.id_avaliacao, a.titulo, a.data_aplicacao,
               DATE_FORMAT(a.data_aplicacao, '%Y-%m') AS periodo
        FROM nota n
        JOIN avaliacao a ON a.id_avaliacao = n.id_avaliacao
        JOIN usuario u   ON u.id_usuario = n.id_aluno
        WHERE a.id_turma = ?";

$tipos   = "i";
$valores = [$idTurma];

if ($idMateria > 0) {
    $sql      .= " AND a.id_materia = ?";
    $tipos    .= "i";
    $valores[] = $idMateria;
}
if ($idAvaliacao > 0) {
    $sql      .= " AND a.id_avaliacao = ?";
    $tipos    .= "i";
    $valores[] = $idAvaliacao;
}
if ($periodo !== '') {
    $sql      .= " AND DATE_FORMAT(a.data_aplicacao, '%Y-%m') = ?";
    $tipos    .= "s";
    $valores[] = $periodo;
}

$sql .= " ORDER BY u.nome ASC, a.data_aplicacao ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($tipos, ...$valores);
$stmt->execute();
$result = $stmt->get_result();

$notas = [];
while ($row = $result->fetch_assoc()) {
    $notas[] = $row;
}
$stmt->close();
$conn->close();

// ── Nenhuma nota no filtro escolhido: devolve a tela vazia, não um erro ─────
if (empty($notas)) {
    responderJson([
        "status"          => "sucesso",
        "media_aprovacao" => MEDIA_APROVACAO,
        "turma" => [
            "id_turma"     => $turma['id_turma'],
            "nome_turma"   => $turma['nome_turma'],
            "ano_letivo"   => $turma['ano_letivo'],
            "total_alunos" => $totalAlunos
        ],
        "filtros" => [
            "materias"   => array_values($materiasDisponiveis),
            "avaliacoes" => $avaliacoesDisponiveis,
            "periodos"   => array_values($periodosDisponiveis)
        ],
        "resumo" => [
            "total_notas"          => 0,
            "total_avaliados"      => 0,
            "media_turma"          => 0.0,
            "maior_nota"           => 0.0,
            "menor_nota"           => 0.0,
            "aprovados"            => 0,
            "reprovados"           => 0,
            "percentual_aprovacao" => 0.0
        ],
        "distribuicao"  => [],
        "por_avaliacao" => [],
        "evolucao"      => [],
        "alunos"        => []
    ]);
}

// ── Agregações (tudo derivado da mesma lista de notas) ──────────────────────
$valoresNotas = array_map(fn($n) => floatval($n['valor']), $notas);

$porAluno     = [];
$porAvaliacao = [];
$porPeriodo   = [];

foreach ($notas as $n) {
    $idAluno = intval($n['id_aluno']);
    $valor   = floatval($n['valor']);

    if (!isset($porAluno[$idAluno])) {
        $porAluno[$idAluno] = [
            "id_aluno" => $idAluno,
            "nome"     => $n['nome_aluno'],
            "valores"  => [],
            "notas"    => []
        ];
    }
    $porAluno[$idAluno]["valores"][] = $valor;
    $porAluno[$idAluno]["notas"][]   = [
        "id_nota"      => intval($n['id_nota']),
        "id_avaliacao" => intval($n['id_avaliacao']),
        "titulo"       => $n['titulo'],
        "data"         => $n['data_aplicacao'],
        "valor"        => arredondar($valor)
    ];

    $idAv = intval($n['id_avaliacao']);
    if (!isset($porAvaliacao[$idAv])) {
        $porAvaliacao[$idAv] = [
            "id_avaliacao" => $idAv,
            "titulo"       => $n['titulo'],
            "data"         => $n['data_aplicacao'],
            "valores"      => []
        ];
    }
    $porAvaliacao[$idAv]["valores"][] = $valor;

    $porPeriodo[$n['periodo']][] = $valor;
}

// Alunos (com a média do filtro atual e as notas que podem ser editadas)
$alunos = [];
foreach ($porAluno as $aluno) {
    $media = array_sum($aluno['valores']) / count($aluno['valores']);
    $alunos[] = [
        "id_aluno" => $aluno['id_aluno'],
        "nome"     => $aluno['nome'],
        "media"    => arredondar($media),
        "aprovado" => estaAprovado($media),
        "notas"    => $aluno['notas']
    ];
}
usort($alunos, fn($a, $b) => $b['media'] <=> $a['media']);

$mediasAlunos = array_column($alunos, 'media');
$aprovados    = count(array_filter($mediasAlunos, 'estaAprovado'));
$totalAvaliados = count($alunos);

// Desempenho por avaliação
$listaAvaliacoes = [];
foreach ($porAvaliacao as $av) {
    $listaAvaliacoes[] = [
        "id_avaliacao" => $av['id_avaliacao'],
        "titulo"       => $av['titulo'],
        "data"         => $av['data'],
        "total_notas"  => count($av['valores']),
        "media"        => arredondar(array_sum($av['valores']) / count($av['valores'])),
        "maior_nota"   => arredondar(max($av['valores'])),
        "menor_nota"   => arredondar(min($av['valores']))
    ];
}
usort($listaAvaliacoes, fn($a, $b) => strcmp($a['data'], $b['data']));

// Evolução ao longo do tempo
ksort($porPeriodo);
$evolucao = [];
foreach ($porPeriodo as $chave => $valores) {
    $evolucao[] = [
        "periodo" => $chave,
        "rotulo"  => rotuloMes($chave),
        "media"   => arredondar(array_sum($valores) / count($valores))
    ];
}

// Distribuição das notas por faixa
$faixas = [
    ["rotulo" => "0 a 2",  "min" => 0.0, "max" => 2.0],
    ["rotulo" => "2 a 4",  "min" => 2.0, "max" => 4.0],
    ["rotulo" => "4 a 6",  "min" => 4.0, "max" => 6.0],
    ["rotulo" => "6 a 8",  "min" => 6.0, "max" => 8.0],
    ["rotulo" => "8 a 10", "min" => 8.0, "max" => 10.0]
];

$distribuicao = [];
foreach ($faixas as $i => $faixa) {
    $ehUltima  = ($i === count($faixas) - 1);
    $quantidade = count(array_filter(
        $valoresNotas,
        fn($v) => $v >= $faixa['min'] && ($ehUltima ? $v <= $faixa['max'] : $v < $faixa['max'])
    ));

    $distribuicao[] = [
        "rotulo"     => $faixa['rotulo'],
        "quantidade" => $quantidade,
        "percentual" => percentual($quantidade, count($valoresNotas))
    ];
}

responderJson([
    "status"          => "sucesso",
    "media_aprovacao" => MEDIA_APROVACAO,
    "turma" => [
        "id_turma"     => $turma['id_turma'],
        "nome_turma"   => $turma['nome_turma'],
        "ano_letivo"   => $turma['ano_letivo'],
        "total_alunos" => $totalAlunos
    ],
    "filtros" => [
        "materias"   => array_values($materiasDisponiveis),
        "avaliacoes" => $avaliacoesDisponiveis,
        "periodos"   => array_values($periodosDisponiveis)
    ],
    "resumo" => [
        "total_notas"          => count($valoresNotas),
        "total_avaliados"      => $totalAvaliados,
        "media_turma"          => arredondar(array_sum($valoresNotas) / count($valoresNotas)),
        "maior_nota"           => arredondar(max($valoresNotas)),
        "menor_nota"           => arredondar(min($valoresNotas)),
        "aprovados"            => $aprovados,
        "reprovados"           => $totalAvaliados - $aprovados,
        "percentual_aprovacao" => percentual($aprovados, $totalAvaliados)
    ],
    "distribuicao"  => $distribuicao,
    "por_avaliacao" => $listaAvaliacoes,
    "evolucao"      => $evolucao,
    "alunos"        => $alunos
]);
