-- Executar uma vez no banco appest (MySQL/MariaDB).
CREATE TABLE IF NOT EXISTS livro_didatico (
 id_livro INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(255) NOT NULL,
 materia VARCHAR(80) NOT NULL,
 fonte_url VARCHAR(500) NOT NULL,
 versao CHAR(64) NOT NULL UNIQUE,
 ativo TINYINT NOT NULL DEFAULT 1,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS frente_livro (
 id_frente INT AUTO_INCREMENT PRIMARY KEY,
 id_livro INT NOT NULL,
 ordem INT NOT NULL,
 titulo VARCHAR(255) NOT NULL,
 FOREIGN KEY (id_livro) REFERENCES livro_didatico(id_livro),
 UNIQUE KEY livro_frente_ordem (id_livro, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS capitulo_livro (
 id_capitulo INT AUTO_INCREMENT PRIMARY KEY,
 id_livro INT NOT NULL,
 id_frente INT NULL,
 ordem INT NOT NULL,
 titulo VARCHAR(255) NOT NULL,
 paginas_json LONGTEXT NOT NULL,
 revisado TINYINT NOT NULL DEFAULT 0,
 FOREIGN KEY (id_livro) REFERENCES livro_didatico(id_livro),
 FOREIGN KEY (id_frente) REFERENCES frente_livro(id_frente),
 UNIQUE KEY frente_capitulo_ordem (id_frente, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS estudo_gerado (
 id_estudo INT AUTO_INCREMENT PRIMARY KEY,
 id_capitulo INT NOT NULL,
 dificuldade VARCHAR(10) NOT NULL,
 versao_gerador VARCHAR(100) NOT NULL,
 conteudo_json LONGTEXT NOT NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_capitulo) REFERENCES capitulo_livro(id_capitulo),
 UNIQUE KEY cache_estudo (id_capitulo, dificuldade, versao_gerador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
