<?php
// Amplia orientações existentes sem apagar o histórico.
foreach (['id_livro' => 'INT NULL', 'bimestre' => 'TINYINT NULL'] as $coluna => $tipo) {
    if (!$conn->query("SHOW COLUMNS FROM prompt_professor LIKE '$coluna'")->num_rows) {
        $conn->query("ALTER TABLE prompt_professor ADD COLUMN `$coluna` $tipo");
    }
}
if (!$conn->query("SHOW INDEX FROM prompt_professor WHERE Key_name='idx_prompt_escopo'")->num_rows) {
    $conn->query('ALTER TABLE prompt_professor ADD INDEX idx_prompt_escopo (id_turma,materia,id_livro,usado)');
}
