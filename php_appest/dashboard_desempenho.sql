-- ============================================================================
-- Dashboard de Desempenho das Turmas
-- Rode este script uma vez no banco "appest" (o mesmo usado em conexao.php).
--
--   mysql -u root appest < dashboard_desempenho.sql
--
-- O script é aditivo: usa CREATE TABLE IF NOT EXISTS e não altera nem apaga
-- nenhuma tabela já existente do sistema.
-- ============================================================================

-- ── Sessões de login ────────────────────────────────────────────────────────
-- Guarda o token entregue ao app no login. É o que permite o backend saber
-- QUEM está chamando o endpoint, em vez de confiar no id que o app manda.
CREATE TABLE IF NOT EXISTS sessao (
    id_sessao   INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT         NOT NULL,
    token       CHAR(64)    NOT NULL,
    criado_em   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em   DATETIME    NOT NULL,
    ativo       TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uk_sessao_token (token),
    KEY idx_sessao_usuario (id_usuario),
    CONSTRAINT fk_sessao_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

-- ── Avaliações aplicadas em uma turma ───────────────────────────────────────
-- Uma avaliação pertence a UMA turma. Como a turma pertence a um professor,
-- é por esse caminho (nota -> avaliacao -> turma -> id_professor) que a
-- autorização é validada no servidor.
CREATE TABLE IF NOT EXISTS avaliacao (
    id_avaliacao   INT AUTO_INCREMENT PRIMARY KEY,
    id_turma       INT           NOT NULL,
    id_materia     INT           NULL,
    titulo         VARCHAR(120)  NOT NULL,
    data_aplicacao DATE          NOT NULL,
    criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_avaliacao_turma (id_turma),
    KEY idx_avaliacao_materia (id_materia),
    KEY idx_avaliacao_data (data_aplicacao),
    CONSTRAINT fk_avaliacao_turma   FOREIGN KEY (id_turma)   REFERENCES turma(id_turma),
    CONSTRAINT fk_avaliacao_materia FOREIGN KEY (id_materia) REFERENCES materia(id_materia)
);

-- ── Nota de um aluno em uma avaliação ───────────────────────────────────────
-- A constraint UNIQUE impede duas notas do mesmo aluno na mesma avaliação
-- (protege contra requisição duplicada). O CHECK garante a escala 0..10 no
-- próprio banco, além da validação feita no PHP.
CREATE TABLE IF NOT EXISTS nota (
    id_nota        INT AUTO_INCREMENT PRIMARY KEY,
    id_avaliacao   INT           NOT NULL,
    id_aluno       INT           NOT NULL,
    valor          DECIMAL(4,2)  NOT NULL,
    atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por INT           NULL,
    UNIQUE KEY uk_nota_avaliacao_aluno (id_avaliacao, id_aluno),
    KEY idx_nota_aluno (id_aluno),
    CONSTRAINT fk_nota_avaliacao FOREIGN KEY (id_avaliacao) REFERENCES avaliacao(id_avaliacao),
    CONSTRAINT fk_nota_aluno     FOREIGN KEY (id_aluno)     REFERENCES usuario(id_usuario),
    CONSTRAINT ck_nota_valor     CHECK (valor >= 0 AND valor <= 10)
);
