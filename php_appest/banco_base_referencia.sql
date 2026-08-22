-- ============================================================================
-- Estrutura base do banco "appest" — ARQUIVO DE REFERÊNCIA / AMBIENTE LOCAL
--
-- ATENÇÃO: este script foi reconstruído a partir das consultas dos endpoints
-- PHP já existentes no projeto (login.php, listar_turmas_professor.php,
-- listar_desempenho_turma.php, etc.), porque o repositório não tinha um dump
-- do schema.
--
-- Se você JÁ tem o banco "appest" funcionando, NÃO precisa rodar este arquivo.
-- Ele existe para conseguir subir o projeto do zero em uma máquina nova e
-- para rodar os testes do dashboard. Todos os comandos são
-- CREATE ... IF NOT EXISTS, ou seja, não sobrescrevem nada já existente.
--
--     mysql -u root < banco_base_referencia.sql
--     mysql -u root appest < dashboard_desempenho.sql
--     mysql -u root appest < prompt_professor.sql
--     php seed_dashboard_demo.php
-- ============================================================================

CREATE DATABASE IF NOT EXISTS appest
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE appest;

CREATE TABLE IF NOT EXISTS usuario (
    id_usuario  INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(120) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    senha       VARCHAR(255) NOT NULL,
    tipo_perfil VARCHAR(20)  NOT NULL DEFAULT 'aluno',
    UNIQUE KEY uk_usuario_email (email)
);

CREATE TABLE IF NOT EXISTS turma (
    id_turma     INT AUTO_INCREMENT PRIMARY KEY,
    nome_turma   VARCHAR(80) NOT NULL,
    ano_letivo   VARCHAR(10) NOT NULL,
    id_professor INT         NOT NULL,
    KEY idx_turma_professor (id_professor),
    CONSTRAINT fk_turma_professor FOREIGN KEY (id_professor) REFERENCES usuario(id_usuario)
);

CREATE TABLE IF NOT EXISTS matricula (
    id_matricula INT AUTO_INCREMENT PRIMARY KEY,
    id_aluno     INT NOT NULL,
    id_turma     INT NOT NULL,
    UNIQUE KEY uk_matricula_aluno_turma (id_aluno, id_turma),
    CONSTRAINT fk_matricula_aluno FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_matricula_turma FOREIGN KEY (id_turma) REFERENCES turma(id_turma)
);

CREATE TABLE IF NOT EXISTS materia (
    id_materia INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS questao (
    id_questao  INT AUTO_INCREMENT PRIMARY KEY,
    id_materia  INT          NOT NULL,
    enunciado   TEXT         NOT NULL,
    dificuldade VARCHAR(20)  NOT NULL DEFAULT 'MEDIO',
    CONSTRAINT fk_questao_materia FOREIGN KEY (id_materia) REFERENCES materia(id_materia)
);

CREATE TABLE IF NOT EXISTS alternativa (
    id_alternativa INT AUTO_INCREMENT PRIMARY KEY,
    id_questao     INT          NOT NULL,
    texto          VARCHAR(255) NOT NULL,
    eh_correta     TINYINT(1)   NOT NULL DEFAULT 0,
    CONSTRAINT fk_alternativa_questao FOREIGN KEY (id_questao) REFERENCES questao(id_questao)
);

CREATE TABLE IF NOT EXISTS ranking (
    id_ranking     INT AUTO_INCREMENT PRIMARY KEY,
    id_aluno       INT        NOT NULL,
    id_turma       INT        NOT NULL,
    pontos         INT        NOT NULL DEFAULT 0,
    mes_referencia VARCHAR(7) NOT NULL,
    UNIQUE KEY uk_ranking_aluno_turma_mes (id_aluno, id_turma, mes_referencia),
    CONSTRAINT fk_ranking_aluno FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_ranking_turma FOREIGN KEY (id_turma) REFERENCES turma(id_turma)
);

CREATE TABLE IF NOT EXISTS historico (
    id_historico     INT AUTO_INCREMENT PRIMARY KEY,
    id_aluno         INT        NOT NULL,
    id_questao       INT        NULL,
    id_alternativa   INT        NULL,
    acertou          TINYINT(1) NOT NULL DEFAULT 0,
    enunciado_gemini TEXT       NULL,
    CONSTRAINT fk_historico_aluno FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario)
);

CREATE TABLE IF NOT EXISTS historico_quiz (
    id_historico     INT AUTO_INCREMENT PRIMARY KEY,
    id_aluno         INT      NOT NULL,
    materia          VARCHAR(100) NOT NULL,
    acertos          INT      NOT NULL DEFAULT 0,
    total_questoes   INT      NOT NULL DEFAULT 0,
    data_realizacao  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historico_quiz_aluno FOREIGN KEY (id_aluno) REFERENCES usuario(id_usuario)
);
