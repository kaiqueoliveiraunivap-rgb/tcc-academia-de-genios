<?php
// Fila local, um processo por banco; não ocupa a requisição de upload.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('COORD_INTERNO',true);
require __DIR__.'/../coordenacao/interno.php';
set_time_limit(0);
$lock='coord-worker-'.configuracao('DB_NAME','appest');
if ((int)linhas('SELECT GET_LOCK(?,0) AS ok',[$lock])[0]['ok']!==1) exit;
function executarPdf(array $comando,string $log,int $timeout=900): void {
    $p=proc_open($comando,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('Não foi possível iniciar o extrator.');
    $inicio=time();
    do {
        $st=proc_get_status($p);
        if (!$st['running']) break;
        if (time()-$inicio>$timeout) { proc_terminate($p); proc_close($p); throw new RuntimeException('Tempo limite excedido ao extrair PDF.'); }
        usleep(200000);
    } while (true);
    proc_close($p);
    if ($st['exitcode']!==0) throw new RuntimeException('Extrator retornou erro.');
}
function pythonCoord(string $log): string {
    $candidatos=[configuracao('PDF_PYTHON_BIN'),(getenv('USERPROFILE') ?: '').'/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe','python3','python'];
    foreach ($candidatos as $bin) {
        if (!$bin || (str_contains($bin,'/') && !is_file($bin))) continue;
        try { executarPdf([$bin,'-c','import pypdf'],$log,20); return $bin; } catch (Throwable $e) { }
    }
    throw new RuntimeException('Python com pypdf não disponível. Configure PDF_PYTHON_BIN.');
}
try {
    // O lock exclusivo garante que não existe outro worker usando o trabalho interrompido.
    cq("UPDATE coordenacao_livro SET estado='fila' WHERE estado='processando'");
    while ($job=linhas("SELECT * FROM coordenacao_livro WHERE estado='fila' ORDER BY id LIMIT 1")[0] ?? null) {
        $id=(int)$job['id']; $base=pastaLivros().'/'.$job['arquivo']; $log=$base.'.log';
        cq("UPDATE coordenacao_livro SET estado='processando',mensagem='Extraindo frentes e capítulos…' WHERE id=?",[$id]);
        try {
            $python=pythonCoord($log);
            $url='/php_appest/livro_pdf.php?arquivo='.$job['arquivo'];
            $cmd=[$python,__DIR__.'/preparar_pdf.py',$base.'.pdf',$base.'.json','--titulo',$job['titulo'],'--materia',$job['materia'],'--fonte-url',$url];
            if (is_file($base.'.mapa.json')) array_push($cmd,'--mapa',$base.'.mapa.json');
            executarPdf($cmd,$log);
            executarPdf([PHP_BINARY,__DIR__.'/importar_livro.php',$base.'.json'],$log,120);
            $livro=linhas('SELECT id_livro FROM livro_didatico WHERE fonte_url=? AND ativo=1 ORDER BY id_livro DESC LIMIT 1',[$url])[0] ?? null;
            if (!$livro) throw new RuntimeException('Livro não localizado após importação.');
            cq("UPDATE coordenacao_livro SET estado='pronto',mensagem='Disponível no aplicativo.',id_livro=? WHERE id=?",[$livro['id_livro'],$id]);
        } catch (Throwable $e) {
            error_log('Importação '.$id.': '.$e->getMessage());
            $msg='Não foi possível publicar. Confira se o PDF tem texto selecionável e indique as páginas dos capítulos em Revisar. PDFs digitalizados precisam passar por OCR antes do envio.';
            if (str_contains($e->getMessage(),'PDF_PYTHON_BIN')) $msg='Extrator indisponível. Configure Python com pypdf no servidor (PDF_PYTHON_BIN).';
            cq("UPDATE coordenacao_livro SET estado='revisao',mensagem=? WHERE id=?",[$msg,$id]);
        }
    }
} finally { cq('SELECT RELEASE_LOCK(?)',[$lock]); }
