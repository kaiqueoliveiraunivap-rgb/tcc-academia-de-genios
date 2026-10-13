<?php
// Leitura direta das páginas revisadas do livro, sem geração nem serviço externo.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/perfis.php';

$aluno = exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil']) !== 'aluno') responderErro('Leitura exclusiva de alunos.', 403);
$id = inteiroDoPost('id_capitulo');
if (!$id) responderErro('Capítulo inválido.', 422);

$stmt = $conn->prepare('SELECT c.titulo AS capitulo, c.paginas_json,
                              l.titulo AS livro, l.materia, l.fonte_url
                       FROM capitulo_livro c
                       JOIN livro_didatico l ON l.id_livro = c.id_livro
                       WHERE c.id_capitulo = ? AND c.revisado = 1 AND l.ativo = 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$capitulo = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$capitulo) responderErro('Capítulo não disponível para leitura.', 404);

// Capítulos classificados só podem ser lidos por alunos da série vinculada.
// Capítulos ainda sem classificação continuam no fluxo legado de transição.
$s=$conn->prepare('SELECT 1 FROM conteudo_capitulo cc JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo
    WHERE cc.id_capitulo=? LIMIT 1');
$s->bind_param('i',$id); $s->execute(); $classificado=$s->get_result()->num_rows>0; $s->close();
if ($classificado) {
    $s=$conn->prepare('SELECT 1 FROM conteudo_capitulo cc JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo
        JOIN turma t ON t.serie=cp.serie JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
        WHERE cc.id_capitulo=? AND cp.ativo=1 AND cp.materia=? LIMIT 1');
    $s->bind_param('iis',$aluno['id_usuario'],$id,$capitulo['materia']);
    $s->execute(); $permitido=$s->get_result()->num_rows>0; $s->close();
    if (!$permitido) responderErro('Conteúdo indisponível para sua série.',403);
}

$paginas = json_decode($capitulo['paginas_json'], true);
if (!is_array($paginas) || !$paginas) responderErro('Este capítulo ainda não possui páginas legíveis.', 422);

responderJson([
    'status' => 'sucesso',
    'id_capitulo' => $id,
    'livro' => $capitulo['livro'],
    'capitulo' => $capitulo['capitulo'],
    'materia' => $capitulo['materia'],
    'fonte_url' => $capitulo['fonte_url'],
    'paginas' => $paginas,
    'ia_disponivel' => configuracao('GEMINI_MODEL') !== '' && configuracao('GEMINI_API_KEY') !== '',
]);
