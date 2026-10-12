ALTER TABLE turma ADD COLUMN IF NOT EXISTS serie VARCHAR(40) NULL AFTER nome_turma;
UPDATE turma SET serie = nome_turma WHERE serie IS NULL OR TRIM(serie) = '';

ALTER TABLE historico ADD COLUMN IF NOT EXISTS materia VARCHAR(100) NULL AFTER enunciado_gemini;
ALTER TABLE historico ADD COLUMN IF NOT EXISTS id_capitulo INT NULL AFTER materia;
ALTER TABLE historico ADD COLUMN IF NOT EXISTS respondido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id_capitulo;
ALTER TABLE historico ADD KEY IF NOT EXISTS idx_historico_capitulo (id_capitulo);

CREATE TABLE IF NOT EXISTS professor_materia (
    id_professor INT NOT NULL,
    materia VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_professor, materia),
    CONSTRAINT fk_professor_materia_usuario FOREIGN KEY (id_professor) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
