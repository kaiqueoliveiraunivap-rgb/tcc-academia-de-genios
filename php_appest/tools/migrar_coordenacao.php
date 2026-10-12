<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!$conn->query("SHOW COLUMNS FROM usuario LIKE 'excluido_em'")->num_rows) {
    $conn->query('ALTER TABLE usuario ADD excluido_em DATETIME NULL, ADD INDEX idx_usuario_perfil_ativo(tipo_perfil,excluido_em)');
}
$conn->query("CREATE TABLE IF NOT EXISTS coordenacao_sessao (
 token_hash CHAR(64) PRIMARY KEY, id_usuario INT NOT NULL, csrf CHAR(64) NOT NULL,
 expira_em DATETIME NOT NULL, ultimo_acesso DATETIME NOT NULL,
 INDEX(id_usuario), INDEX(expira_em), FOREIGN KEY(id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS coordenacao_limite (
 chave CHAR(64) PRIMARY KEY, tentativas INT NOT NULL, inicio DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS coordenacao_auditoria (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, id_autor INT NOT NULL, acao VARCHAR(50) NOT NULL,
 id_alvo INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS coordenacao_livro (
 id INT AUTO_INCREMENT PRIMARY KEY, arquivo CHAR(64) NOT NULL UNIQUE, sha256 CHAR(64) NOT NULL UNIQUE,
 titulo VARCHAR(255) NOT NULL, materia VARCHAR(80) NOT NULL, id_autor INT NOT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'fila', mensagem VARCHAR(500) NOT NULL DEFAULT '',
 id_livro INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(estado), FOREIGN KEY(id_livro) REFERENCES livro_didatico(id_livro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
