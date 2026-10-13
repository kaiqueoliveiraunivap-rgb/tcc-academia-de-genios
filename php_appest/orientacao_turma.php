<?php
// A turma e a orientação são resolvidas no servidor a partir da matrícula.
function turmaDoEstudo($conn, int $idAluno, ?int $idTurma): ?int {
    if ($idTurma !== null) {
        exigirAlunoNaTurma($conn,$idAluno,$idTurma);
        return $idTurma;
    }
    $s=$conn->prepare('SELECT MIN(id_turma) AS id_turma FROM matricula WHERE id_aluno=?');
    $s->bind_param('i',$idAluno); $s->execute();
    $id=$s->get_result()->fetch_assoc()['id_turma'];
    return $id === null ? null : (int)$id;
}

function orientacaoDaTurma($conn, ?int $idTurma, string $materia, ?int $idLivro=null, ?int $idConteudo=null): ?array {
    if ($idTurma === null) return null;
    $s=$conn->prepare('SELECT p.id_prompt,p.id_turma,p.materia,p.id_livro,p.bimestre,p.instrucao,p.prompt_final,u.nome AS nome_professor
        FROM prompt_professor p
        JOIN turma t ON t.id_turma=p.id_turma AND t.id_professor=p.id_professor
        JOIN usuario u ON u.id_usuario=p.id_professor
        WHERE p.id_turma=? AND p.materia=? AND (p.id_livro IS NULL OR p.id_livro=?)
          AND (p.id_conteudo IS NULL OR p.id_conteudo=?) AND p.id_aluno IS NULL AND p.usado=0
        ORDER BY (p.id_conteudo IS NOT NULL) DESC,(p.id_livro IS NOT NULL) DESC,p.data_criacao DESC,p.id_prompt DESC LIMIT 1');
    $s->bind_param('isii',$idTurma,$materia,$idLivro,$idConteudo); $s->execute();
    return $s->get_result()->fetch_assoc();
}
