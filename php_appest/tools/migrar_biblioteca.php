<?php
// Migração aditiva e repetível: introduz Frente sem apagar livros ou capítulos.
$conn->query("CREATE TABLE IF NOT EXISTS frente_livro (
 id_frente INT AUTO_INCREMENT PRIMARY KEY,
 id_livro INT NOT NULL,
 ordem INT NOT NULL,
 titulo VARCHAR(255) NOT NULL,
 FOREIGN KEY (id_livro) REFERENCES livro_didatico(id_livro),
 UNIQUE KEY livro_frente_ordem (id_livro, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($conn->query("SHOW COLUMNS FROM capitulo_livro LIKE 'id_frente'")->num_rows===0) {
    $conn->query('ALTER TABLE capitulo_livro ADD COLUMN id_frente INT NULL AFTER id_livro');
}
if ($conn->query("SHOW INDEX FROM capitulo_livro WHERE Key_name='idx_capitulo_frente'")->num_rows===0) {
    $conn->query('ALTER TABLE capitulo_livro ADD INDEX idx_capitulo_frente (id_frente,ordem)');
}
if ($conn->query("SHOW INDEX FROM capitulo_livro WHERE Key_name='idx_capitulo_livro'")->num_rows===0) {
    // Mantém o índice exigido pela FK de id_livro ao substituir o índice legado.
    $conn->query('ALTER TABLE capitulo_livro ADD INDEX idx_capitulo_livro (id_livro)');
}

// Catálogo antigo: cada livro passa a ser uma frente padrão com o mesmo título.
$conn->query("INSERT INTO frente_livro (id_livro,ordem,titulo)
 SELECT l.id_livro,1,l.titulo FROM livro_didatico l
 WHERE NOT EXISTS (SELECT 1 FROM frente_livro f WHERE f.id_livro=l.id_livro)");
$conn->query("UPDATE capitulo_livro c
 JOIN frente_livro f ON f.id_livro=c.id_livro AND f.ordem=1
 SET c.id_frente=f.id_frente WHERE c.id_frente IS NULL");
if ($conn->query("SHOW INDEX FROM capitulo_livro WHERE Key_name='livro_ordem'")->num_rows>0) {
    $conn->query('ALTER TABLE capitulo_livro DROP INDEX livro_ordem');
}
if ($conn->query("SHOW INDEX FROM capitulo_livro WHERE Key_name='frente_capitulo_ordem'")->num_rows===0) {
    $conn->query('ALTER TABLE capitulo_livro ADD UNIQUE KEY frente_capitulo_ordem (id_frente,ordem)');
}
