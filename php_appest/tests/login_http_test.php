<?php
// Usa banco próprio e contas temporárias: nunca altera as contas de appest.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db='appest_test_login_'.bin2hex(random_bytes(5));
$admin=new mysqli(configuracao('DB_HOST','127.0.0.1'),configuracao('DB_USER','root'),configuracao('DB_PASSWORD'),'',(int)configuracao('DB_PORT',3306));
$admin->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$env=array_merge(getenv(),['DB_NAME'=>$db,'APP_ENV'=>'production']);
$base='http://127.0.0.1:8098/php_appest/';
$contas=[]; $server=null; $conn=null; $tokens=[];
function executarInstalacaoLogin(array $env): void {
    $p=proc_open([PHP_BINARY,__DIR__.'/../tools/instalar.php'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
    if (!is_resource($p)) throw new RuntimeException('Instalador de teste não iniciou.');
    fclose($pipes[0]); $saida=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $erro=stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($p)!==0) throw new RuntimeException('Instalação isolada falhou: '.$erro.' '.$saida);
}
function postTeste($endpoint,$dados) {
    global $base;
    $c=curl_init($base.$endpoint);
    curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($dados),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
    $body=curl_exec($c); $status=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    return [$status,json_decode($body,true,512,JSON_THROW_ON_ERROR)];
}
function checar($ok,$mensagem) { if (!$ok) throw new RuntimeException($mensagem); }
try {
    executarInstalacaoLogin($env);
    putenv('DB_NAME='.$db); putenv('APP_ENV=production');
    require __DIR__.'/../conexao.php';
    foreach (['aluno','professor','admin'] as $perfil) {
        $email=$perfil.'-'.bin2hex(random_bytes(5)).'@teste.invalid';
        $senha='Teste-'.bin2hex(random_bytes(10)).'Aa1';
        $hash=password_hash($senha,PASSWORD_DEFAULT);
        $s=$conn->prepare('INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)');
        $nome='Teste '.ucfirst($perfil); $s->bind_param('ssss',$nome,$email,$hash,$perfil); $s->execute();
        $contas[]=['perfil'=>$perfil,'email'=>$email,'senha'=>$senha,'id'=>$s->insert_id];
    }
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:8098','-t',dirname(__DIR__,2)],
        [0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__,2),$env);
    if (!is_resource($server)) throw new RuntimeException('API de teste não iniciou.');
    fclose($pipes[0]);
    $pronto=false;
    for ($i=0;$i<50;$i++) {
        $socket=@fsockopen('127.0.0.1',8098,$errno,$errstr,.1);
        if ($socket) { fclose($socket); $pronto=true; break; }
        usleep(100000);
    }
    if (!$pronto) throw new RuntimeException('API isolada não respondeu.');
    foreach ($contas as $c) {
        foreach (['aluno','professor','admin'] as $perfil) {
            [$http,$r]=postTeste('login.php',['email'=>$c['email'],'senha'=>$c['senha'],'tipo_perfil'=>$perfil]);
            if ($perfil==='admin') {
                checar($http===403 && !isset($r['token']),'Coordenação deveria entrar apenas pelo painel web.');
            } elseif ($c['perfil']===$perfil) {
                checar($http===200 && ($r['tipo_perfil']??'')===$perfil && strlen($r['token']??'')===64,'Login correto falhou: '.$perfil);
                $tokens[$perfil]=$r['token'];
            } else checar($http===401 && !isset($r['token']),'Perfil incorreto recebeu acesso.');
        }
    }
    foreach (['','diretor'] as $perfil) {
        [$http,$r]=postTeste('login.php',['email'=>$contas[0]['email'],'senha'=>$contas[0]['senha'],'tipo_perfil'=>$perfil]);
        checar($http===422 && !isset($r['token']),'Perfil inválido aceito.');
    }
    [$http]=postTeste('login.php',['email'=>$contas[0]['email'],'senha'=>'senha-errada','tipo_perfil'=>'aluno']);
    checar($http===401,'Senha errada aceita.');
    foreach ($tokens as $perfil=>$token) {
        [$http,$r]=postTeste('admin.php',['token'=>$token]);
        checar($http===($perfil==='admin'?200:403),'Permissão de coordenação incorreta.');
    }
    // Várias matrículas não podem transformar um login válido em inválido.
    $prof=$contas[1]['id'];
    $aluno=$contas[0]['id'];
    $conn->query("INSERT INTO turma (nome_turma,ano_letivo,id_professor) VALUES ('Teste HTTP temporário','2026',".(int)$prof.")");
    $turma=$conn->insert_id;
    $conn->query('INSERT INTO matricula (id_turma,id_aluno) VALUES ('.(int)$turma.','.(int)$aluno.')');
    [$http,$r]=postTeste('login.php',['email'=>$contas[0]['email'],'senha'=>$contas[0]['senha'],'tipo_perfil'=>'aluno']);
    checar($http===200,'Aluno com múltiplas matrículas não entrou.');
    if (isset($r['token'])) postTeste('logout.php',['token'=>$r['token']]);
    echo "OK: logins de aluno/professor, perfis trocados recusados, coordenação exclusiva do painel, senha errada e múltiplas matrículas.\n";
} finally {
    if ($server) foreach ($tokens as $token) postTeste('logout.php',['token'=>$token]);
    if (isset($turma) && $conn) {
        $conn->query('DELETE FROM matricula WHERE id_turma='.(int)$turma);
        $conn->query('DELETE FROM turma WHERE id_turma='.(int)$turma);
    }
    if ($server) { proc_terminate($server); proc_close($server); }
    if ($conn) $conn->close();
    if (preg_match('/^appest_test_login_[a-f0-9]{10}$/D',$db)) $admin->query("DROP DATABASE `$db`");
    $admin->close();
}
