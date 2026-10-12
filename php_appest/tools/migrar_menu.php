<?php
// Executado pelo instalador local; aditivo e repetível.
function adicionarColunaMenu($conn, $tabela, $nome, $definicao) {
    $r=$conn->query("SHOW COLUMNS FROM `$tabela` LIKE '$nome'");
    if ($r->num_rows===0) $conn->query("ALTER TABLE `$tabela` ADD COLUMN `$nome` $definicao");
}
adicionarColunaMenu($conn,'turma','serie',"VARCHAR(80) NOT NULL DEFAULT ''");
adicionarColunaMenu($conn,'estudo_gerado','id_destinatario','INT NULL');
adicionarColunaMenu($conn,'estudo_gerado','id_turma_destinataria','INT NULL');
// Mantém orientações individuais antigas como histórico, sem aplicá-las à turma.
$colunaAluno=$conn->query("SHOW COLUMNS FROM prompt_professor LIKE 'id_aluno'")->fetch_assoc();
if ($colunaAluno['Null']!=='YES') $conn->query('ALTER TABLE prompt_professor MODIFY id_aluno INT NULL');
if ($conn->query("SHOW INDEX FROM prompt_professor WHERE Key_name='idx_prompt_turma_materia'")->num_rows===0) {
    $conn->query('ALTER TABLE prompt_professor ADD INDEX idx_prompt_turma_materia (id_turma,materia,usado,id_aluno)');
}
// Só reconhece série quando o próprio nome da turma já é explicitamente um ano escolar.
$conn->query("UPDATE turma SET serie=nome_turma WHERE serie='' AND nome_turma REGEXP '^[1-9].{0,2} [Aa][Nn][Oo]$'");
$conn->query("UPDATE turma SET serie='3º Ano' WHERE serie='' AND nome_turma='Turma de desenvolvimento' AND id_professor IN (SELECT id_usuario FROM usuario WHERE email='professor.local@academia.test')");
$conn->query("CREATE TABLE IF NOT EXISTS quiz_estudo (
 id_quiz INT AUTO_INCREMENT PRIMARY KEY,
 id_aluno INT NOT NULL,
 id_turma INT NULL,
 id_estudo INT NOT NULL,
 tentativa CHAR(36) NOT NULL,
 acertos INT NOT NULL,
 total INT NOT NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY tentativa_aluno (id_aluno,tentativa),
 FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario),
 FOREIGN KEY (id_turma) REFERENCES turma(id_turma),
 FOREIGN KEY (id_estudo) REFERENCES estudo_gerado(id_estudo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS resposta_estudo (
 id_resposta INT AUTO_INCREMENT PRIMARY KEY,
 id_quiz INT NOT NULL,
 ordem INT NOT NULL,
 assunto VARCHAR(255) NOT NULL,
 acertou TINYINT NOT NULL,
 UNIQUE KEY quiz_ordem (id_quiz,ordem),
 FOREIGN KEY (id_quiz) REFERENCES quiz_estudo(id_quiz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Índices para histórico paginado e progresso, sem ler o JSON das explicações.
adicionarColunaMenu($conn,'resposta_estudo','alternativa_escolhida','TINYINT UNSIGNED NULL');
if ($conn->query("SHOW INDEX FROM quiz_estudo WHERE Key_name='idx_quiz_aluno_estudo'")->num_rows===0) {
    $conn->query('ALTER TABLE quiz_estudo ADD INDEX idx_quiz_aluno_estudo (id_aluno,id_estudo)');
}
if ($conn->query("SHOW INDEX FROM quiz_estudo WHERE Key_name='idx_quiz_aluno_id'")->num_rows===0) {
    $conn->query('ALTER TABLE quiz_estudo ADD INDEX idx_quiz_aluno_id (id_aluno,id_quiz)');
}

// O dump original não tinha uma chave que identificasse a pontuação mensal de
// um aluno. Antes de criá-la, consolida eventuais linhas repetidas preservando
// a soma dos pontos já conquistados.
$indiceRanking=$conn->query("SHOW INDEX FROM ranking WHERE Key_name='ranking_aluno_turma_mes'");
if ($indiceRanking->num_rows===0) {
    $conn->query("CREATE TEMPORARY TABLE ranking_consolidado AS
        SELECT MIN(id_ranking) AS manter, id_aluno, id_turma, mes_referencia,
               SUM(pontos) AS total
        FROM ranking
        GROUP BY id_aluno,id_turma,mes_referencia");
    $conn->query("UPDATE ranking r JOIN ranking_consolidado c ON c.manter=r.id_ranking
        SET r.pontos=c.total");
    $conn->query("DELETE r FROM ranking r JOIN ranking_consolidado c
        ON c.id_aluno=r.id_aluno AND c.id_turma=r.id_turma
        AND c.mes_referencia <=> r.mes_referencia
        WHERE r.id_ranking<>c.manter");
    $conn->query("DROP TEMPORARY TABLE ranking_consolidado");
    $conn->query("ALTER TABLE ranking ADD UNIQUE KEY ranking_aluno_turma_mes
        (id_aluno,id_turma,mes_referencia)");
}
