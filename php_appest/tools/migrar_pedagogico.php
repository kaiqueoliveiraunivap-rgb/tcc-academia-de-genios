<?php
// Migração aditiva. Nenhum capítulo recebe série ou bimestre por inferência.
// Executada pelo instalador CLI depois das tabelas legadas.
if (PHP_SAPI !== 'cli' || !isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(404);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS conteudo_pedagogico (
    id_conteudo INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    serie VARCHAR(80) NOT NULL,
    materia VARCHAR(80) NOT NULL,
    bimestre TINYINT UNSIGNED NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conteudo_catalogo (serie,materia,ativo,bimestre,ordem),
    CONSTRAINT ck_conteudo_bimestre CHECK (bimestre IS NULL OR bimestre BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS conteudo_capitulo (
    id_conteudo INT NOT NULL,
    id_capitulo INT NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_conteudo,id_capitulo),
    INDEX idx_conteudo_capitulo_capitulo (id_capitulo),
    FOREIGN KEY (id_conteudo) REFERENCES conteudo_pedagogico(id_conteudo),
    FOREIGN KEY (id_capitulo) REFERENCES capitulo_livro(id_capitulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS progresso_conteudo (
    id_aluno INT NOT NULL,
    id_conteudo INT NOT NULL,
    id_capitulo_atual INT NULL,
    iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_aluno,id_conteudo),
    INDEX idx_progresso_recente (id_aluno,ultimo_acesso),
    FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario),
    FOREIGN KEY (id_conteudo) REFERENCES conteudo_pedagogico(id_conteudo),
    FOREIGN KEY (id_capitulo_atual) REFERENCES capitulo_livro(id_capitulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS atividade_publicada (
    id_atividade INT AUTO_INCREMENT PRIMARY KEY,
    id_conteudo INT NOT NULL,
    id_turma INT NOT NULL,
    id_professor INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    orientacao TEXT NULL,
    publicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    prazo_em DATETIME NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_atividade_turma (id_turma,ativa,publicada_em),
    INDEX idx_atividade_conteudo (id_conteudo),
    FOREIGN KEY (id_conteudo) REFERENCES conteudo_pedagogico(id_conteudo),
    FOREIGN KEY (id_turma) REFERENCES turma(id_turma),
    FOREIGN KEY (id_professor) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS atividade_quiz (
    id_atividade INT NOT NULL,
    id_quiz INT NOT NULL,
    PRIMARY KEY (id_atividade,id_quiz),
    UNIQUE KEY uk_atividade_quiz_tentativa (id_quiz),
    FOREIGN KEY (id_atividade) REFERENCES atividade_publicada(id_atividade),
    FOREIGN KEY (id_quiz) REFERENCES quiz_estudo(id_quiz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Os pontos antigos permanecem intactos. Esta tabela controla apenas as
// melhores pontuações dos meses processados após a nova regra entrar em vigor.
$conn->query("CREATE TABLE IF NOT EXISTS ranking_melhor_conteudo (
    id_aluno INT NOT NULL,
    id_turma INT NOT NULL,
    mes_referencia CHAR(7) NOT NULL,
    chave_conteudo VARCHAR(80) NOT NULL,
    pontos INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_aluno,id_turma,mes_referencia,chave_conteudo),
    FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario),
    FOREIGN KEY (id_turma) REFERENCES turma(id_turma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
