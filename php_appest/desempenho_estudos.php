<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/perfis.php';

$usuario=exigirUsuarioLogado($conn);
$perfil=normalizarPerfil($usuario['tipo_perfil']);
$nivel=trim((string)($_POST['nivel'] ?? 'materias'));
$materia=trim((string)($_POST['materia'] ?? ''));

if (!in_array($perfil,['aluno','professor'],true)) responderErro('Perfil sem acesso a este relatório.',403);
if (!in_array($nivel,['materias','conteudos'],true)) responderErro('Relatório inválido.',422);
if ($nivel==='conteudos' && ($materia==='' || mb_strlen($materia)>80)) responderErro('Matéria inválida.',422);

$idTurma=null;
if ($perfil==='professor') {
    $idTurma=inteiroDoPost('id_turma');
    if (!$idTurma) responderErro('Turma inválida.',422);
    exigirTurmaDoProfessor($conn,$idTurma,(int)$usuario['id_usuario']);
}

$campo=$nivel==='materias' ? 'l.materia' : 'r.assunto';
$filtroMateria=$nivel==='conteudos' ? ' AND l.materia=?' : '';
$filtroPerfil=$perfil==='professor' ? 'q.id_turma=?' : 'q.id_aluno=?';
$sql="SELECT $campo AS nome, l.materia,
             COUNT(*) AS respostas, COALESCE(SUM(r.acertou),0) AS acertos,
             COUNT(DISTINCT q.id_quiz) AS quizzes,
             COUNT(DISTINCT q.id_aluno) AS alunos
      FROM resposta_estudo r
      JOIN quiz_estudo q ON q.id_quiz=r.id_quiz
      JOIN estudo_gerado e ON e.id_estudo=q.id_estudo
      JOIN capitulo_livro c ON c.id_capitulo=e.id_capitulo
      JOIN livro_didatico l ON l.id_livro=c.id_livro
      WHERE $filtroPerfil$filtroMateria
      GROUP BY $campo,l.materia
      ORDER BY l.materia,$campo";

$stmt=$conn->prepare($sql);
$idFiltro=$perfil==='professor' ? $idTurma : (int)$usuario['id_usuario'];
if ($nivel==='conteudos') $stmt->bind_param('is',$idFiltro,$materia);
else $stmt->bind_param('i',$idFiltro);
$stmt->execute();
$resultado=$stmt->get_result();
$dados=[];
$totalRespostas=0;
$totalAcertos=0;
while ($row=$resultado->fetch_assoc()) {
    $respostas=(int)$row['respostas'];
    $acertos=(int)$row['acertos'];
    $erros=$respostas-$acertos;
    $dados[]=[
        'nome'=>$row['nome'],
        'materia'=>$row['materia'],
        'respostas'=>$respostas,
        'acertos'=>$acertos,
        'erros'=>$erros,
        'percentual_acertos'=>$respostas ? round($acertos*100/$respostas,1) : 0,
        'percentual_erros'=>$respostas ? round($erros*100/$respostas,1) : 0,
        'quizzes'=>(int)$row['quizzes'],
        'alunos'=>(int)$row['alunos'],
    ];
    $totalRespostas+=$respostas;
    $totalAcertos+=$acertos;
}

responderJson([
    'status'=>'sucesso',
    'nivel'=>$nivel,
    'materia'=>$materia,
    'itens'=>$dados,
    'resumo'=>[
        'respostas'=>$totalRespostas,
        'acertos'=>$totalAcertos,
        'erros'=>$totalRespostas-$totalAcertos,
        'percentual_acertos'=>$totalRespostas ? round($totalAcertos*100/$totalRespostas,1) : 0,
    ],
]);
