-- ============================================================================
-- Cadastro de professor
-- Rode este script uma vez no banco "appest" (o mesmo usado em conexao.php).
--
--   mysql -u root appest < cadastro_professor.sql
--
-- O script é aditivo: usa CREATE TABLE IF NOT EXISTS e não altera nem apaga
-- nenhuma tabela já existente do sistema.
-- ============================================================================

-- ── Convites de cadastro de professor ───────────────────────────────────────
-- Por que existe: sem isto, qualquer pessoa com o endereço do servidor poderia
-- se cadastrar como professor. O perfil "professor" abre o dashboard de notas e
-- o envio de orientações para a IA, então ele não pode ser auto-atribuído.
--
-- O código NÃO é guardado em texto puro: fica como hash, do mesmo jeito que uma
-- senha. Assim um dump do banco não entrega códigos de cadastro válidos.
-- Use criar_convite_professor.php para gerar um código novo.
CREATE TABLE IF NOT EXISTS convite_professor (
    id_convite   INT AUTO_INCREMENT PRIMARY KEY,
    codigo_hash  VARCHAR(255) NOT NULL,
    descricao    VARCHAR(120) NOT NULL,
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em    DATETIME     NULL,
    usos_maximos INT          NOT NULL DEFAULT 1,
    usos         INT          NOT NULL DEFAULT 0,
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    KEY idx_convite_ativo (ativo)
);

-- ── Rastreio de qual convite criou cada professor ───────────────────────────
-- Permite auditar depois quem entrou por qual convite, sem alterar a tabela
-- "usuario" (que é usada por todas as telas antigas).
CREATE TABLE IF NOT EXISTS convite_professor_uso (
    id_uso     INT AUTO_INCREMENT PRIMARY KEY,
    id_convite INT      NOT NULL,
    id_usuario INT      NOT NULL,
    usado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uso_usuario (id_usuario),
    KEY idx_uso_convite (id_convite),
    CONSTRAINT fk_uso_convite FOREIGN KEY (id_convite) REFERENCES convite_professor(id_convite),
    CONSTRAINT fk_uso_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);
