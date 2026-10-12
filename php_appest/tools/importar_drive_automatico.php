<?php
/** Importa uma vez os PDFs do Drive; o app consulta somente o banco pronto.
 * Uso: php tools/importar_drive_automatico.php --todos [--reprocess]
 *      php tools/importar_drive_automatico.php --id ID_DO_DRIVE [--reprocess]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexao.php';

$raiz=dirname(__DIR__,2);
$catalogo=json_decode(file_get_contents($raiz.'/biblioteca/catalogo-drive.json'),true,512,JSON_THROW_ON_ERROR);
$args=$argv; array_shift($args);
$todos=in_array('--todos',$args,true);
$reprocessar=in_array('--reprocess',$args,true);
$pos=array_search('--id',$args,true);
$id=$pos===false ? null : ($args[$pos+1] ?? null);
if (!$todos && (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]+$/',$id))) {
    fwrite(STDERR,"Informe --id ID_DO_DRIVE ou --todos.\n"); exit(2);
}
$itens=array_values(array_filter($catalogo,fn($i)=>$todos || ($i['id'] ?? '')===$id));
if (!$itens) { fwrite(STDERR,"Livro não encontrado no catálogo.\n"); exit(2); }

function pythonDisponivel(): string {
    $perfil=getenv('USERPROFILE') ?: '';
    $candidatos=[getenv('PDF_PYTHON_BIN') ?: '',
        $perfil.'/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe',
        'python3','python'];
    foreach($candidatos as $bin) {
        if ($bin==='' || (str_contains($bin,'/') && !is_file($bin))) continue;
        $saida=[]; $codigo=1;
        exec(escapeshellarg($bin).' -c "import pypdf" 2>&1',$saida,$codigo);
        if ($codigo===0) return $bin;
    }
    throw new RuntimeException('Python com pypdf não encontrado. Defina PDF_PYTHON_BIN no servidor.');
}

function pdfValido(string $arquivo): bool {
    if (!is_file($arquivo) || filesize($arquivo)<1024) return false;
    $f=fopen($arquivo,'rb'); $cabecalho=fread($f,5); fclose($f);
    return $cabecalho==='%PDF-';
}

function baixarPdf(string $id,string $arquivo): void {
    if (pdfValido($arquivo)) return;
    $parcial=$arquivo.'.parcial';
    $f=fopen($parcial,'wb');
    if (!$f) throw new RuntimeException('Não foi possível abrir o destino privado do PDF.');
    $url='https://drive.usercontent.google.com/download?id='.rawurlencode($id).'&export=download&confirm=t';
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_FILE=>$f,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>300,CURLOPT_MAXFILESIZE=>250000000]);
    $ok=curl_exec($curl); $http=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $erro=curl_error($curl); curl_close($curl); fclose($f);
    if (!$ok || $http!==200 || !pdfValido($parcial)) {
        @unlink($parcial);
        throw new RuntimeException('Falha no download do Drive (HTTP '.$http.'): '.$erro);
    }
    if (!rename($parcial,$arquivo)) throw new RuntimeException('Não foi possível guardar o PDF baixado.');
}

function jaDisponivel(mysqli $conn,string $url): bool {
    $s=$conn->prepare('SELECT 1 FROM livro_didatico l JOIN capitulo_livro c ON c.id_livro=l.id_livro WHERE l.fonte_url=? AND l.ativo=1 AND c.revisado=1 LIMIT 1');
    $s->bind_param('s',$url); $s->execute();
    return $s->get_result()->num_rows>0;
}

$python=pythonDisponivel();
$dir=$raiz.'/.runtime/pdfs';
if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Não foi possível criar o cache privado.');
$falhas=[]; $concluidos=0; $ignorados=0;
foreach($itens as $item) {
    try {
        if (!$reprocessar && jaDisponivel($conn,$item['url'])) { $ignorados++; continue; }
        $arquivo=$dir.'/'.$item['id'].'.pdf';
        $json=$dir.'/'.$item['id'].'.prepared.json';
        baixarPdf($item['id'],$arquivo);
        $cmd=escapeshellarg($python).' '.escapeshellarg(__DIR__.'/preparar_pdf.py')
            .' '.escapeshellarg($arquivo).' '.escapeshellarg($json)
            .' --titulo '.escapeshellarg($item['titulo'])
            .' --materia '.escapeshellarg($item['materia'])
            .' --fonte-url '.escapeshellarg($item['url']).' 2>&1';
        $saida=[]; $codigo=1; exec($cmd,$saida,$codigo);
        if ($codigo!==0) throw new RuntimeException(implode(' ',$saida));
        $preparado=json_decode(file_get_contents($json),true,512,JSON_THROW_ON_ERROR);
        $total=array_sum(array_map(fn($f)=>count($f['capitulos']),$preparado['frentes']));
        $saida=[]; $codigo=1;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/importar_livro.php').' '.escapeshellarg($json).' 2>&1',$saida,$codigo);
        if ($codigo!==0) throw new RuntimeException(implode(' ',$saida));
        $concluidos++;
        echo $item['materia'].' — '.$item['titulo'].': '.count($preparado['frentes']).' frente(s), '.$total." capítulo(s).\n";
    } catch (Throwable $e) {
        $falhas[]=['livro'=>$item['titulo'],'motivo'=>$e->getMessage()];
        fwrite(STDERR,$item['titulo'].': '.$e->getMessage()."\n");
    }
}
echo "Concluídos: $concluidos; já disponíveis: $ignorados; pendentes: ".count($falhas).".\n";
if ($falhas) exit(1);
