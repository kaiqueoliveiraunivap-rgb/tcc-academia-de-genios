-- Esquema oficial do banco appest, baseado no dump fornecido em 21/09/2026.
-- Não contém INSERTs, senhas, tokens de sessão nem dados pessoais.
-- O instalador aplica este arquivo somente quando o banco está vazio.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alternativa` (
  `id_alternativa` int(11) NOT NULL AUTO_INCREMENT,
  `id_questao` int(11) NOT NULL,
  `texto` text NOT NULL,
  `eh_correta` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_alternativa`),
  KEY `id_questao` (`id_questao`),
  CONSTRAINT `alternativa_ibfk_1` FOREIGN KEY (`id_questao`) REFERENCES `questao` (`id_questao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `avaliacao` (
  `id_avaliacao` int(11) NOT NULL AUTO_INCREMENT,
  `id_turma` int(11) NOT NULL,
  `id_materia` int(11) DEFAULT NULL,
  `titulo` varchar(120) NOT NULL,
  `data_aplicacao` date NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_avaliacao`),
  KEY `idx_avaliacao_turma` (`id_turma`),
  KEY `idx_avaliacao_materia` (`id_materia`),
  KEY `idx_avaliacao_data` (`data_aplicacao`),
  CONSTRAINT `fk_avaliacao_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id_materia`),
  CONSTRAINT `fk_avaliacao_turma` FOREIGN KEY (`id_turma`) REFERENCES `turma` (`id_turma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capitulo_livro` (
  `id_capitulo` int(11) NOT NULL AUTO_INCREMENT,
  `id_livro` int(11) NOT NULL,
  `id_frente` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `paginas_json` longtext NOT NULL,
  `revisado` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_capitulo`),
  UNIQUE KEY `frente_capitulo_ordem` (`id_frente`,`ordem`),
  KEY `idx_capitulo_frente` (`id_frente`,`ordem`),
  KEY `idx_capitulo_livro` (`id_livro`),
  CONSTRAINT `capitulo_livro_ibfk_1` FOREIGN KEY (`id_livro`) REFERENCES `livro_didatico` (`id_livro`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `convite_professor` (
  `id_convite` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_hash` varchar(255) NOT NULL,
  `descricao` varchar(120) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `expira_em` datetime DEFAULT NULL,
  `usos_maximos` int(11) NOT NULL DEFAULT 1,
  `usos` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_convite`),
  KEY `idx_convite_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `convite_professor_uso` (
  `id_uso` int(11) NOT NULL AUTO_INCREMENT,
  `id_convite` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `usado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_uso`),
  UNIQUE KEY `uk_uso_usuario` (`id_usuario`),
  KEY `idx_uso_convite` (`id_convite`),
  CONSTRAINT `fk_uso_convite` FOREIGN KEY (`id_convite`) REFERENCES `convite_professor` (`id_convite`),
  CONSTRAINT `fk_uso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estudo_gerado` (
  `id_estudo` int(11) NOT NULL AUTO_INCREMENT,
  `id_capitulo` int(11) NOT NULL,
  `dificuldade` varchar(10) NOT NULL,
  `versao_gerador` varchar(100) NOT NULL,
  `conteudo_json` longtext NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_destinatario` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_estudo`),
  UNIQUE KEY `cache_estudo` (`id_capitulo`,`dificuldade`,`versao_gerador`),
  CONSTRAINT `estudo_gerado_ibfk_1` FOREIGN KEY (`id_capitulo`) REFERENCES `capitulo_livro` (`id_capitulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frente_livro` (
  `id_frente` int(11) NOT NULL AUTO_INCREMENT,
  `id_livro` int(11) NOT NULL,
  `ordem` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_frente`),
  UNIQUE KEY `livro_frente_ordem` (`id_livro`,`ordem`),
  CONSTRAINT `frente_livro_ibfk_1` FOREIGN KEY (`id_livro`) REFERENCES `livro_didatico` (`id_livro`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historico` (
  `id_historico` int(11) NOT NULL AUTO_INCREMENT,
  `id_aluno` int(11) NOT NULL,
  `id_questao` int(11) DEFAULT NULL,
  `id_alternativa` int(11) DEFAULT NULL,
  `acertou` tinyint(1) DEFAULT NULL,
  `enunciado_gemini` text DEFAULT NULL,
  `materia` varchar(100) DEFAULT NULL,
  `id_capitulo` int(11) DEFAULT NULL,
  `respondido_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historico`),
  KEY `id_aluno` (`id_aluno`),
  KEY `id_questao` (`id_questao`),
  KEY `id_alternativa` (`id_alternativa`),
  KEY `idx_historico_capitulo` (`id_capitulo`),
  CONSTRAINT `historico_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `historico_ibfk_2` FOREIGN KEY (`id_questao`) REFERENCES `questao` (`id_questao`),
  CONSTRAINT `historico_ibfk_3` FOREIGN KEY (`id_alternativa`) REFERENCES `alternativa` (`id_alternativa`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historico_quiz` (
  `id_historico` int(11) NOT NULL AUTO_INCREMENT,
  `id_aluno` int(11) NOT NULL,
  `materia` varchar(100) NOT NULL,
  `acertos` int(11) NOT NULL,
  `total_questoes` int(11) NOT NULL,
  `data_realizacao` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historico`),
  KEY `id_aluno` (`id_aluno`),
  CONSTRAINT `historico_quiz_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instalacao_local` (
  `chave` varchar(80) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_didatico` (
  `id_livro` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `materia` varchar(80) NOT NULL,
  `fonte_url` varchar(500) NOT NULL,
  `versao` char(64) NOT NULL,
  `ativo` tinyint(4) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_livro`),
  UNIQUE KEY `versao` (`versao`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materia` (
  `id_materia` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id_materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `matricula` (
  `id_matricula` int(11) NOT NULL AUTO_INCREMENT,
  `id_turma` int(11) NOT NULL,
  `id_aluno` int(11) NOT NULL,
  PRIMARY KEY (`id_matricula`),
  KEY `id_turma` (`id_turma`),
  KEY `id_aluno` (`id_aluno`),
  CONSTRAINT `matricula_ibfk_1` FOREIGN KEY (`id_turma`) REFERENCES `turma` (`id_turma`),
  CONSTRAINT `matricula_ibfk_2` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nota` (
  `id_nota` int(11) NOT NULL AUTO_INCREMENT,
  `id_avaliacao` int(11) NOT NULL,
  `id_aluno` int(11) NOT NULL,
  `valor` decimal(4,2) NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_nota`),
  UNIQUE KEY `uk_nota_avaliacao_aluno` (`id_avaliacao`,`id_aluno`),
  KEY `idx_nota_aluno` (`id_aluno`),
  CONSTRAINT `fk_nota_aluno` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_nota_avaliacao` FOREIGN KEY (`id_avaliacao`) REFERENCES `avaliacao` (`id_avaliacao`),
  CONSTRAINT `ck_nota_valor` CHECK (`valor` >= 0 and `valor` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `professor_materia` (
  `id_professor` int(11) NOT NULL,
  `materia` varchar(100) NOT NULL,
  PRIMARY KEY (`id_professor`,`materia`),
  CONSTRAINT `fk_professor_materia_usuario` FOREIGN KEY (`id_professor`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prompt_professor` (
  `id_prompt` int(11) NOT NULL AUTO_INCREMENT,
  `id_professor` int(11) NOT NULL,
  `id_aluno` int(11) NOT NULL,
  `id_turma` int(11) NOT NULL,
  `materia` varchar(100) NOT NULL,
  `dificuldade` varchar(20) NOT NULL DEFAULT 'MEDIO',
  `instrucao` text NOT NULL,
  `prompt_final` text NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_prompt`),
  KEY `fk_prompt_professor` (`id_professor`),
  KEY `fk_prompt_aluno` (`id_aluno`),
  KEY `fk_prompt_turma` (`id_turma`),
  CONSTRAINT `fk_prompt_aluno` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_prompt_professor` FOREIGN KEY (`id_professor`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_prompt_turma` FOREIGN KEY (`id_turma`) REFERENCES `turma` (`id_turma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questao` (
  `id_questao` int(11) NOT NULL AUTO_INCREMENT,
  `enunciado` text NOT NULL,
  `dificuldade` varchar(20) DEFAULT NULL,
  `id_materia` int(11) NOT NULL,
  PRIMARY KEY (`id_questao`),
  KEY `id_materia` (`id_materia`),
  CONSTRAINT `questao_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id_materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_estudo` (
  `id_quiz` int(11) NOT NULL AUTO_INCREMENT,
  `id_aluno` int(11) NOT NULL,
  `id_turma` int(11) DEFAULT NULL,
  `id_estudo` int(11) NOT NULL,
  `tentativa` char(36) NOT NULL,
  `acertos` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_quiz`),
  UNIQUE KEY `tentativa_aluno` (`id_aluno`,`tentativa`),
  KEY `id_turma` (`id_turma`),
  KEY `id_estudo` (`id_estudo`),
  CONSTRAINT `quiz_estudo_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `quiz_estudo_ibfk_2` FOREIGN KEY (`id_turma`) REFERENCES `turma` (`id_turma`),
  CONSTRAINT `quiz_estudo_ibfk_3` FOREIGN KEY (`id_estudo`) REFERENCES `estudo_gerado` (`id_estudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ranking` (
  `id_ranking` int(11) NOT NULL AUTO_INCREMENT,
  `id_aluno` int(11) NOT NULL,
  `id_turma` int(11) NOT NULL,
  `pontos` int(11) DEFAULT 0,
  `mes_referencia` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`id_ranking`),
  KEY `id_aluno` (`id_aluno`),
  KEY `id_turma` (`id_turma`),
  CONSTRAINT `ranking_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `ranking_ibfk_2` FOREIGN KEY (`id_turma`) REFERENCES `turma` (`id_turma`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resposta_estudo` (
  `id_resposta` int(11) NOT NULL AUTO_INCREMENT,
  `id_quiz` int(11) NOT NULL,
  `ordem` int(11) NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `acertou` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_resposta`),
  UNIQUE KEY `quiz_ordem` (`id_quiz`,`ordem`),
  CONSTRAINT `resposta_estudo_ibfk_1` FOREIGN KEY (`id_quiz`) REFERENCES `quiz_estudo` (`id_quiz`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessao` (
  `id_sessao` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `token` char(64) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `expira_em` datetime NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sessao`),
  UNIQUE KEY `uk_sessao_token` (`token`),
  KEY `idx_sessao_usuario` (`id_usuario`),
  CONSTRAINT `fk_sessao_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turma` (
  `id_turma` int(11) NOT NULL AUTO_INCREMENT,
  `nome_turma` varchar(100) NOT NULL,
  `serie` varchar(40) DEFAULT NULL,
  `ano_letivo` varchar(20) NOT NULL,
  `id_professor` int(11) NOT NULL,
  PRIMARY KEY (`id_turma`),
  KEY `id_professor` (`id_professor`),
  CONSTRAINT `turma_ibfk_1` FOREIGN KEY (`id_professor`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_perfil` varchar(20) NOT NULL DEFAULT 'aluno',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
