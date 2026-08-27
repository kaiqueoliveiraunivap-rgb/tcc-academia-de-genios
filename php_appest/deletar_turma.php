<?php
// Apaga uma turma do professor autenticado.
//
// Antes rodava sem login nenhum: qualquer requisição com um id_turma apagava a
// turma correspondente, de qualquer professor.

require_once 'auth.php';

$professor = exigirProfessor($conn);

$idTurma = inteiroDoPost('id_turma');

if ($idTurma === null) {
    responderErro("Turma inválida.", 422);
}

exigirTurmaDoProfessor($conn, $idTurma, $professor['id_usuario']);

// A turma é referenciada por matricula e ranking. Apagar direto quebraria a
// FK e devolveria erro de banco; remover os dependentes primeiro, na mesma
// transação, deixa o banco consistente se algo falhar no meio.
$conn->begin_transaction();

try {
    foreach (["DELETE FROM ranking WHERE id_turma = ?",
              "DELETE FROM matricula WHERE id_turma = ?"] as $sqlDependente) {
        $stmt = $conn->prepare($sqlDependente);
        $stmt->bind_param("i", $idTurma);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM turma WHERE id_turma = ? AND id_professor = ?");
    $stmt->bind_param("ii", $idTurma, $professor['id_usuario']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("deletar_turma: falha ao apagar turma $idTurma: " . $e->getMessage());
    responderErro("Não foi possível apagar a turma.", 500);
}

responderJson(["status" => "deletado"]);
