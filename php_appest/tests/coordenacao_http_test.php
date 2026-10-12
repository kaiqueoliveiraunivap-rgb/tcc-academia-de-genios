<?php
// Integração HTTP em banco aleatório. --visual mantém a instância isolada para testar a interface.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
function ck($ok,string $m): void { if (!$ok) throw new RuntimeException($m); }
function runCoord(array $cmd,array $env): void {
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
    ck(proc_close($p)===0,'Subprocesso falhou: '.$err.' '.$out);
}
function req($curl,string $acao,?array $data=null,int $status=200,string $csrf='',bool $form=false): array {
    $url='http://127.0.0.1:8097/php_appest/'.$acao;
    $headers=['X-Coord-Request: 1','X-CSRF-Token: '.$csrf];
    if (!$form) $headers[]='Content-Type: application/json';
    curl_setopt_array($curl,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POST=>$data!==null]);
    if ($data!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,$form?$data:json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $raw=curl_exec($curl);$http=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    ck($http===$status,"$acao esperava $status, recebeu $http: ".$raw);
    return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
}
function op($c,string $acao,array $data=[],int $status=200,string $csrf='',bool $form=false): array { return req($c,'coordenacao/api.php',array_merge(['acao'=>$acao],$data),$status,$csrf,$form); }
function cliente(){ $c=curl_init();curl_setopt($c,CURLOPT_COOKIEFILE,'');return $c; }
function processarFixture(array $env, mysqli $banco, int $id): void {
    global $c;
    if ($env['COORD_WORKER_EXTERNO']==='1') {
        runCoord([PHP_BINARY,__DIR__.'/../tools/coordenacao_worker.php'],$env);
        return;
    }
    $limite=microtime(true)+60; $proximaConsulta=0;
    do {
        $estado=$banco->query('SELECT estado FROM coordenacao_livro WHERE id='.$id)->fetch_assoc()['estado'];
        if (!in_array($estado,['fila','processando'],true)) return;
        // Reproduz a consulta do painel, inclusive a recuperação de um worker interrompido.
        if (microtime(true)>=$proximaConsulta) {
            req($c,'coordenacao/api.php?acao=livros');
            $proximaConsulta=microtime(true)+5;
        }
        usleep(200000);
    } while (microtime(true)<$limite);
    throw new RuntimeException('O processamento automático do PDF não terminou em 60 segundos.');
}
function fixturePdf(string $path,bool $marcador): void {
    $texto=str_repeat('Uma razao compara duas grandezas por divisao. Numerador e denominador devem ter unidades compativeis. A proporcao expressa igualdade entre duas razoes e pode ser resolvida por multiplicacao cruzada. ',5);
    $stream="BT /F1 12 Tf 50 760 Td 16 TL ";foreach(explode("\n",wordwrap($texto,80)) as $linha)$stream.='('.$linha.") Tj T* ";$stream.='ET';
    $objs=['<< /Type /Catalog /Pages 2 0 R'.($marcador?' /Outlines 6 0 R':'').' >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
    if($marcador)array_push($objs,'<< /Type /Outlines /First 7 0 R /Last 7 0 R /Count 1 >>','<< /Title (Capitulo 1 - Razoes) /Parent 6 0 R /Dest [3 0 R /Fit] >>');
    $pdf="%PDF-1.4\n";$offsets=[0];foreach($objs as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $off)$pdf.=sprintf("%010d 00000 n \n",$off);$pdf.='trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";file_put_contents($path,$pdf);
}
$db='appest_test_coord_'.bin2hex(random_bytes(5));$dir=dirname(__DIR__,2).'/.runtime/'.$db;
mkdir($dir,0700,true);$server=null;
$admin=new mysqli(configuracao('DB_HOST','127.0.0.1'),configuracao('DB_USER','root'),configuracao('DB_PASSWORD'),'',(int)configuracao('DB_PORT',3306));
$env=array_merge(getenv(),['DB_NAME'=>$db,'APP_ENV'=>'production','COORD_STORAGE_DIR'=>$dir,'COORD_WORKER_EXTERNO'=>in_array('--auto-worker',$argv,true)?'0':'1']);
$criado=false;
try {
    $admin->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$criado=true;
    runCoord([PHP_BINARY,__DIR__.'/../tools/instalar.php'],$env);$admin->select_db($db);$admin->set_charset('utf8mb4');
    $senha='Teste-Web-123!';$hash=password_hash($senha,PASSWORD_DEFAULT);$s=$admin->prepare("INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES ('Coordenação de Teste','coordenacao@teste.invalid',?,'admin')");$s->bind_param('s',$hash);$s->execute();$idAdmin=(int)$s->insert_id;
    $env['APP_ENV']='testing';
    $server=proc_open([PHP_BINARY,'-d','upload_max_filesize=128M','-d','post_max_size=132M','-d','upload_tmp_dir='.$dir,'-d','display_errors=Off','-S','127.0.0.1:8097','-t',dirname(__DIR__),__DIR__.'/../tools/router-local.php'],[0=>['file','NUL','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,dirname(__DIR__),$env);
    ck(is_resource($server),'Servidor de teste não iniciou.');
    $pronto=false;for($i=0;$i<50;$i++){ $socket=@fsockopen('127.0.0.1',8097,$errno,$errstr,.1);if($socket){fclose($socket);$pronto=true;break;}usleep(100000); }ck($pronto,'Servidor não respondeu.');
    $c=cliente();$anon=cliente();$mobile=cliente();
    req($anon,'coordenacao/api.php?acao=inicio',null,401);
    $login=op($c,'login',['email'=>'coordenacao@teste.invalid','senha'=>$senha]);$csrf=$login['csrf'];
    ck(!isset($login['usuario']['senha']) && strlen($csrf)===64,'Login vazou hash ou perdeu CSRF.');
    ck($login['usuario']['foto']===false,'Conta nova veio com foto indevida.');
    $foto=$dir.'/foto.png';file_put_contents($foto,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='));
    op($c,'foto_perfil',['foto'=>new CURLFile($foto,'image/png','foto.png')],403,'',true);
    op($c,'foto_perfil',['foto'=>new CURLFile($foto,'image/png','foto.png')],200,$csrf,true);
    ck(req($c,'coordenacao/api.php?acao=sessao')['usuario']['foto']===true,'Foto não apareceu na sessão.');
    $fotoUrl='http://127.0.0.1:8097/php_appest/coordenacao/api.php?acao=minha_foto';
    curl_setopt_array($c,[CURLOPT_URL=>$fotoUrl,CURLOPT_POST=>false,CURLOPT_HTTPHEADER=>[],CURLOPT_RETURNTRANSFER=>true]);
    $conteudo=curl_exec($c);ck(curl_getinfo($c,CURLINFO_HTTP_CODE)===200 && $conteudo===file_get_contents($foto),'Foto privada não foi entregue corretamente.');
    curl_setopt_array($anon,[CURLOPT_URL=>$fotoUrl,CURLOPT_POST=>false,CURLOPT_HTTPHEADER=>[],CURLOPT_RETURNTRANSFER=>true]);
    curl_exec($anon);ck(curl_getinfo($anon,CURLINFO_HTTP_CODE)===401,'Foto ficou pública.');
    op($c,'remover_foto',[],200,$csrf);
    ck(req($c,'coordenacao/api.php?acao=sessao')['usuario']['foto']===false,'Foto não foi removida.');
    op($c,'salvar_usuario',['nome'=>'Teste','email'=>'teste@teste.invalid','senha'=>$senha,'perfil'=>'aluno'],403);
    $prof=op($c,'salvar_usuario',['nome'=>'Professor Teste','email'=>'prof@teste.invalid','senha'=>$senha,'perfil'=>'professor'],200,$csrf)['id'];
    op($c,'salvar_turma',['serie'=>'1ª série','letra'=>'A','ano_letivo'=>'2026','id_professor'=>$prof],200,$csrf);
    $listaTurmas=req($c,'coordenacao/api.php?acao=turmas')['itens'];
    $turma=$listaTurmas[0]['id_turma'];
    ck($listaTurmas[0]['serie']==='1ª série' && $listaTurmas[0]['letra']==='A' && $listaTurmas[0]['nome_turma']==='1ª série A','Série e letra não foram salvas separadamente no formulário.');
    op($c,'salvar_turma',['id'=>$turma,'serie'=>'2ª série','letra'=>'B','ano_letivo'=>'2026','id_professor'=>$prof],200,$csrf);
    $editada=req($c,'coordenacao/api.php?acao=turmas')['itens'][0];
    ck($editada['serie']==='2ª série' && $editada['letra']==='B' && $editada['nome_turma']==='2ª série B','Edição da série/letra não atualizou a turma.');
    op($c,'salvar_turma',['serie'=>'1ª série','letra'=>'AB','ano_letivo'=>'2026','id_professor'=>$prof],422,$csrf);
    $aluno=op($c,'salvar_usuario',['nome'=>'Aluno Teste','email'=>'aluno@teste.invalid','senha'=>$senha,'perfil'=>'aluno','turmas'=>[$turma]],200,$csrf)['id'];
    foreach ([['aluno','aluno@teste.invalid',$aluno],['professor','prof@teste.invalid',$prof]] as [$perfilApp,$emailApp,$idApp]) {
        $app=cliente();
        $loginApp=req($app,'login.php',['email'=>$emailApp,'senha'=>$senha,'tipo_perfil'=>$perfilApp],200,'',true);
        $tokenApp=$loginApp['token'];
        $conta=req($app,'minha_conta.php',['token'=>$tokenApp,'id_usuario'=>999999],200,'',true);
        ck(($conta['usuario']['id_usuario'] ?? 0)===$idApp && ($conta['usuario']['tipo_perfil'] ?? '')===$perfilApp && count($conta['turmas'])===1,'Minha conta exibiu dados incorretos.');
        req($app,'atualizar_usuario.php',['token'=>$tokenApp,'nome'=>'Nome indevido','email'=>'indevido@teste.invalid'],403,'',true);
        req($app,'logout.php',['token'=>$tokenApp],200,'',true);
    }
    $registro=$admin->query('SELECT senha FROM usuario WHERE id_usuario='.$aluno)->fetch_assoc();ck($registro['senha']!==$senha&&password_verify($senha,$registro['senha']),'Senha não está em hash.');
    $app=req($mobile,'login.php',['email'=>'aluno@teste.invalid','senha'=>$senha,'tipo_perfil'=>'aluno'],200,'',true);$token=$app['token'];ck((int)$app['id_turma']===(int)$turma,'Matrícula do painel não chegou ao app.');
    req($mobile,'login.php',['email'=>'prof@teste.invalid','senha'=>$senha,'tipo_perfil'=>'professor'],200,'',true);
    req($mobile,'login.php',['email'=>'aluno@teste.invalid','senha'=>$senha,'tipo_perfil'=>'professor'],401,'',true);
    req($mobile,'login.php',['email'=>'coordenacao@teste.invalid','senha'=>$senha,'tipo_perfil'=>'admin'],403,'',true);
    op($anon,'login',['email'=>'aluno@teste.invalid','senha'=>$senha],401);
    op($c,'salvar_usuario',['id'=>$aluno,'nome'=>'Aluno Editado','email'=>'aluno@teste.invalid','senha'=>'','perfil'=>'aluno','turmas'=>[$turma]],200,$csrf);
    ck($admin->query('SELECT senha FROM usuario WHERE id_usuario='.$aluno)->fetch_assoc()['senha']===$registro['senha'],'Editar apagou a senha.');
    op($c,'salvar_usuario',['id'=>$aluno,'nome'=>'Aluno Editado','email'=>'aluno@teste.invalid','senha'=>'','perfil'=>'professor'],422,$csrf);
    $csv=$dir.'/usuarios.csv';file_put_contents($csv,"nome;email;senha;turmas\r\nAluno CSV;csv@teste.invalid;$senha;$turma\r\n");
    $upload=['perfil'=>'aluno','csv'=>new CURLFile($csv,'text/csv','usuarios.csv')];
    $prev=op($c,'csv',$upload,200,$csrf,true);ck($prev['total']===1&&!isset($prev['previa'][0]['senha']),'Prévia vazou senha.');
    ck($admin->query("SELECT 1 FROM usuario WHERE email='csv@teste.invalid'")->num_rows===0,'Prévia salvou usuários.');
    op($c,'csv',$upload+['confirmar'=>'1'],200,$csrf,true);
    file_put_contents($csv,"nome;email;senha\nNovo;novo@teste.invalid;$senha\nDuplicado;csv@teste.invalid;$senha\n");
    op($c,'csv',$upload+['confirmar'=>'1'],422,$csrf,true);
    ck($admin->query("SELECT 1 FROM usuario WHERE email='novo@teste.invalid'")->num_rows===0,'CSV inválido salvou parcialmente.');
    op($c,'excluir_usuario',['id'=>$prof],422,$csrf);
    op($c,'excluir_usuario',['id'=>$aluno],200,$csrf);
    req($mobile,'login.php',['email'=>'aluno@teste.invalid','senha'=>$senha,'tipo_perfil'=>'aluno'],401,'',true);
    req($mobile,'historico_estudos.php',['token'=>$token],401,'',true);
    $profToken=req($mobile,'login.php',['email'=>'prof@teste.invalid','senha'=>$senha,'tipo_perfil'=>'professor'],200,'',true)['token'];
    req($mobile,'inserir_matricula.php',['token'=>$profToken,'id_aluno'=>$aluno,'id_turma'=>$turma],404,'',true);
    ck(count(req($c,'coordenacao/api.php?acao=usuarios&perfil=aluno')['itens'])===1,'Conta excluída continua ativa.');
    $equipe=op($c,'salvar_usuario',['nome'=>'Secretaria Teste','email'=>'secretaria@teste.invalid','senha'=>$senha,'perfil'=>'admin','senha_atual'=>$senha],200,$csrf)['id'];
    $outro=cliente();op($outro,'login',['email'=>'secretaria@teste.invalid','senha'=>$senha]);
    op($c,'salvar_usuario',['id'=>$equipe,'nome'=>'Secretaria Teste','email'=>'secretaria@teste.invalid','senha'=>'Nova-Senha-456!','perfil'=>'admin','senha_atual'=>$senha],200,$csrf);
    req($outro,'coordenacao/api.php?acao=inicio',null,401);
    op($outro,'login',['email'=>'secretaria@teste.invalid','senha'=>'Nova-Senha-456!']);
    op($c,'excluir_usuario',['id'=>$idAdmin,'senha_atual'=>$senha],422,$csrf);
    op($c,'excluir_usuario',['id'=>$equipe,'senha_atual'=>$senha],200,$csrf);
    req($outro,'coordenacao/api.php?acao=inicio',null,401);
    $pdf=$dir.'/fixture.pdf';fixturePdf($pdf,true);
    $j=op($c,'enviar_livro',['titulo'=>'Matemática de teste','materia'=>'Matemática','pdf'=>new CURLFile($pdf,'application/pdf','livro.pdf')],200,$csrf,true);
    op($c,'enviar_livro',['titulo'=>'Duplicado','materia'=>'Matemática','pdf'=>new CURLFile($pdf,'application/pdf','livro.pdf')],409,$csrf,true);
    processarFixture($env,$admin,(int)$j['id']);
    $job=$admin->query('SELECT estado,id_livro FROM coordenacao_livro WHERE id='.(int)$j['id'])->fetch_assoc();ck($job['estado']==='pronto','PDF com marcadores não foi publicado.');
    $l=(int)$job['id_livro'];ck($admin->query('SELECT 1 FROM capitulo_livro WHERE revisado=1 AND id_livro='.$l)->num_rows===1,'Livro publicado sem capítulo.');
    $csvAluno=req($mobile,'login.php',['email'=>'csv@teste.invalid','senha'=>$senha,'tipo_perfil'=>'aluno'],200,'',true)['token'];
    $frentes=req($mobile,'listar_frentes.php',['token'=>$csvAluno,'materia'=>'Matemática'],200,'',true);ck(count($frentes['frentes'])===1,'Upload não apareceu na seleção do aluno.');
    $capituloId=(int)$admin->query('SELECT id_capitulo FROM capitulo_livro WHERE id_livro='.$l.' LIMIT 1')->fetch_assoc()['id_capitulo'];
    $leitura=req($mobile,'ler_capitulo.php',['token'=>$csvAluno,'id_capitulo'=>$capituloId],200,'',true);
    ck(($leitura['id_capitulo'] ?? 0)===$capituloId && count($leitura['paginas'] ?? [])>0 && !empty($leitura['paginas'][0]['texto']),'A leitura local não entregou as páginas do PDF.');
    ck((int)$admin->query('SELECT COUNT(*) AS total FROM estudo_gerado')->fetch_assoc()['total']===0,'A leitura local iniciou geração por IA.');
    req($mobile,'ler_capitulo.php',['token'=>$profToken,'id_capitulo'=>$capituloId],403,'',true);
    req($mobile,'ler_capitulo.php',['token'=>$csvAluno,'id_capitulo'=>999999],404,'',true);
    fixturePdf($pdf,false);
    $j=op($c,'enviar_livro',['titulo'=>'Livro sem marcadores','materia'=>'Matemática','pdf'=>new CURLFile($pdf,'application/pdf','livro.pdf')],200,$csrf,true);
    processarFixture($env,$admin,(int)$j['id']);
    ck($admin->query('SELECT estado FROM coordenacao_livro WHERE id='.(int)$j['id'])->fetch_assoc()['estado']==='revisao','PDF sem marcadores deveria aguardar revisão.');
    op($c,'reprocessar_livro',['id'=>$j['id'],'mapa'=>[['frente'=>'Frente única','titulo'=>'Razões','inicio'=>1,'fim'=>1]]],200,$csrf);
    processarFixture($env,$admin,(int)$j['id']);
    ck($admin->query('SELECT estado FROM coordenacao_livro WHERE id='.(int)$j['id'])->fetch_assoc()['estado']==='pronto','Revisão manual não publicou o PDF.');
    op($c,'livro_ativo',['id'=>$l,'ativo'=>false],200,$csrf);
    $frentes=req($mobile,'listar_frentes.php',['token'=>$csvAluno,'materia'=>'Matemática'],200,'',true);ck(count($frentes['frentes'])===1,'Desativação não foi refletida no app.');
    for($i=0;$i<10;$i++)op($anon,'login',['email'=>'inexistente@teste.invalid','senha'=>'incorreta'],401);
    op($anon,'login',['email'=>'inexistente@teste.invalid','senha'=>'incorreta'],429);
    op($c,'logout',[],200,$csrf);req($c,'coordenacao/api.php?acao=inicio',null,401);
    echo "OK: autenticação, perfil, CSRF, hash, CRUD, revogação, matrícula no app, CSV atômico, PDF automático/revisado, acervo e limite de login.\n";
    if(in_array('--visual',$argv,true)){
        echo "Instância visual isolada pronta em http://127.0.0.1:8097/php_appest/coordenacao/\n";
        file_put_contents(dirname(__DIR__,2).'/.runtime/coordenacao-teste-ativo.txt',$dir);
        $inicio=time();while(time()-$inicio<3600&&!is_file($dir.'/encerrar'))sleep(1);
    }
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($criado&&preg_match('/^appest_test_coord_[a-f0-9]{10}$/D',$db))$admin->query("DROP DATABASE `$db`");
    // Artefatos diagnósticos ficam somente na pasta privada ignorada pelo Git.
}
