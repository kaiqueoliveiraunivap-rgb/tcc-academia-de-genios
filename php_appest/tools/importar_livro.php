<?php
// CLI somente. Nunca expor importação ou revisão aos alunos.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexao.php';
try {
    if ($argc!==2) throw new RuntimeException('Uso: php tools/importar_livro.php livro.json');
    $raw=file_get_contents($argv[1]);
    $livro=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    foreach (['titulo','materia','fonte_url','sha256_pdf'] as $k) if (!is_string($livro[$k] ?? null) || trim($livro[$k])==='') throw new RuntimeException('Campo ausente: '.$k);
    $fonteValida=preg_match('~^https://drive\.google\.com/file/d/[a-zA-Z0-9_-]+~',$livro['fonte_url']) || preg_match('~^/php_appest/livro_pdf\.php\?arquivo=[a-f0-9]{64}$~D',$livro['fonte_url']);
    if (strlen($livro['titulo'])>255 || strlen($livro['materia'])>80 || strlen($livro['fonte_url'])>500 || !$fonteValida) throw new RuntimeException('Metadados inválidos.');
    $frentes=$livro['frentes'] ?? null;
    if ($frentes===null && !empty($livro['capitulos'])) {
        // Compatibilidade com os JSONs antigos: o livro inteiro vira uma frente.
        $frentes=[['titulo'=>$livro['frente'] ?? $livro['titulo'],'capitulos'=>$livro['capitulos']]];
    }
    if (!is_array($frentes) || !$frentes) throw new RuntimeException('Sem frentes ou capítulos.');
    $conn->set_charset('utf8mb4');
    // Inclui texto e revisão: alterações produzem nova versão, sem reutilizar cache.
    $versao=hash('sha256',json_encode($livro,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $conn->begin_transaction();
    $s=$conn->prepare('SELECT id_livro FROM livro_didatico WHERE versao=? LIMIT 1');$s->bind_param('s',$versao);$s->execute();
    if($s->get_result()->num_rows>0) { $conn->commit(); echo "Versão já importada.\n"; exit(0); }
    // Reaproveita o cadastro criado pelo catálogo quando ele ainda não possui conteúdo.
    $s=$conn->prepare('SELECT l.id_livro FROM livro_didatico l WHERE l.fonte_url=? AND NOT EXISTS(SELECT 1 FROM capitulo_livro c WHERE c.id_livro=l.id_livro) LIMIT 1');
    $s->bind_param('s',$livro['fonte_url']);$s->execute();$rascunho=$s->get_result()->fetch_assoc();
    if($rascunho){
        $id=(int)$rascunho['id_livro'];
        $s=$conn->prepare('DELETE FROM frente_livro WHERE id_livro=?');$s->bind_param('i',$id);$s->execute();
        $s=$conn->prepare('UPDATE livro_didatico SET titulo=?,materia=?,versao=?,ativo=1 WHERE id_livro=?');
        $s->bind_param('sssi',$livro['titulo'],$livro['materia'],$versao,$id);$s->execute();
    }else{
        $s=$conn->prepare('INSERT INTO livro_didatico (titulo,materia,fonte_url,versao) VALUES (?,?,?,?)');
        $s->bind_param('ssss',$livro['titulo'],$livro['materia'],$livro['fonte_url'],$versao);$s->execute();$id=$conn->insert_id;
    }
    $ocupadas=[];
    foreach ($frentes as $indiceFrente=>$frente) {
        $tituloFrente=trim((string)($frente['titulo'] ?? ''));
        if ($tituloFrente==='' || strlen($tituloFrente)>255 || empty($frente['capitulos']) || !is_array($frente['capitulos'])) throw new RuntimeException('Frente inválida.');
        $ordemFrente=$indiceFrente+1;
        $s=$conn->prepare('INSERT INTO frente_livro (id_livro,ordem,titulo) VALUES (?,?,?)');
        $s->bind_param('iis',$id,$ordemFrente,$tituloFrente); $s->execute(); $idFrente=$conn->insert_id;
        foreach ($frente['capitulos'] as $i=>$cap) {
            if (!is_string($cap['titulo'] ?? null) || trim($cap['titulo'])==='' || strlen($cap['titulo'])>255 || empty($cap['paginas'])) throw new RuntimeException('Capítulo inválido.');
            $revisado=($cap['revisado'] ?? false)===true ? 1 : 0;
            foreach ($cap['paginas'] as $pagina) {
                $n=$pagina['pagina'] ?? 0;
                if (!is_int($n) || $n<1 || isset($ocupadas[$n]) || !is_string($pagina['texto'] ?? null)) throw new RuntimeException('Página inválida ou repetida.');
                $ocupadas[$n]=true;
            }
            $json=json_encode($cap['paginas'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if ($revisado && (strlen($json)>180000 || strlen(implode('',array_column($cap['paginas'],'texto')))<500)) throw new RuntimeException('Texto insuficiente ou extenso demais para publicação.');
            $ordem=$i+1;
            $s=$conn->prepare('INSERT INTO capitulo_livro (id_livro,id_frente,ordem,titulo,paginas_json,revisado) VALUES (?,?,?,?,?,?)');
            $s->bind_param('iiissi',$id,$idFrente,$ordem,$cap['titulo'],$json,$revisado); $s->execute();
        }
    }
    // Preserva o histórico da edição antiga, mas só a nova fica visível.
    $s=$conn->prepare('UPDATE livro_didatico SET ativo=0 WHERE fonte_url=? AND id_livro<>?');
    $s->bind_param('si',$livro['fonte_url'],$id); $s->execute();
    $conn->commit();
    echo "Livro importado: $id. Somente capítulos revisados aparecem no app.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR,'Importação cancelada: '.$e->getMessage()."\n"); exit(1);
}
