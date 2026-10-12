<?php
// Cadastra os PDFs conhecidos do Drive sem publicar conteúdo ainda não revisado.
// Pode ser incluído pelo instalador ou executado diretamente por CLI.
if (!isset($conn)) require_once __DIR__.'/../conexao.php';
$arquivoCatalogo=dirname(__DIR__,2).'/biblioteca/catalogo-drive.json';
if (!is_file($arquivoCatalogo)) throw new RuntimeException('Catálogo dos livros não encontrado.');
$catalogo=json_decode(file_get_contents($arquivoCatalogo),true,512,JSON_THROW_ON_ERROR);
if (!is_array($catalogo)) throw new RuntimeException('Catálogo dos livros inválido.');

$inseridos=0;
$urlsExistentes=[];
$resultado=$conn->query('SELECT fonte_url FROM livro_didatico');
while ($livro=$resultado->fetch_assoc()) $urlsExistentes[$livro['fonte_url']]=true;
$resultado->free();
foreach($catalogo as $item){
    $idDrive=trim((string)($item['id'] ?? ''));
    $titulo=trim((string)($item['titulo'] ?? ''));
    $materia=trim((string)($item['materia'] ?? ''));
    $url=trim((string)($item['url'] ?? ''));
    if($idDrive==='' || $titulo==='' || $materia==='' || !preg_match('~^https://drive\.google\.com/file/d/~',$url)) continue;
    $versao=hash('sha256','catalogo-drive-v1:'.$idDrive);
    if(!isset($urlsExistentes[$url])){
        $s=$conn->prepare('INSERT INTO livro_didatico(titulo,materia,fonte_url,versao,ativo) VALUES(?,?,?,?,1)');
        $s->bind_param('ssss',$titulo,$materia,$url,$versao);$s->execute();$idLivro=$conn->insert_id;$inseridos++;
        $urlsExistentes[$url]=true;
        $frente=preg_replace('/\s*-?\s*@POLIEDRO2024\.pdf$/iu','',$titulo);
        $frente=preg_replace('/\.pdf$/iu','',$frente);
        $s=$conn->prepare('INSERT INTO frente_livro(id_livro,ordem,titulo) VALUES(?,1,?)');
        $s->bind_param('is',$idLivro,$frente);$s->execute();
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) echo "Catálogo sincronizado: $inseridos novo(s) livro(s).\n";
