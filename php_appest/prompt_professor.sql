-- Tabela usada pela tela "Enviar prompt para IA" do professor.
-- Guarda a orientação que o professor escreveu para um aluno e o prompt
-- já formatado que será entregue à Gemini na próxima vez que esse aluno
-- gerar um quiz (ver buscar_prompt_pendente.php).
--
-- Rode este script uma vez no banco "appest" (mesmo banco usado em conexao.php).

CREATE TABLE IF NOT EXISTS prompt_professor (
    id_prompt     INT AUTO_INCREMENT PRIMARY KEY,
    id_professor  INT NOT NULL,
    id_aluno      INT NOT NULL,
    id_turma      INT NOT NULL,
    materia       VARCHAR(100) NOT NULL,
    dificuldade   VARCHAR(20)  NOT NULL DEFAULT 'MEDIO',
    instrucao     TEXT NOT NULL,
    prompt_final  TEXT NOT NULL,
    usado         TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prompt_professor FOREIGN KEY (id_professor) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_prompt_aluno     FOREIGN KEY (id_aluno)     REFERENCES usuario(id_usuario),
    CONSTRAINT fk_prompt_turma     FOREIGN KEY (id_turma)     REFERENCES turma(id_turma)
);
