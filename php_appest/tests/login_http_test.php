<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexao.php';
$base=$argv[1] ?? 'http://127.0.0.1:8088/php_appest/';
$contas=json_decode(file_get_contents(dirname(__DIR__,2).'/.runtime/acessos-locais.json'),true,512,JSON_THROW_ON_ERROR);
function postTeste($endpoint,$dados) {
    global $base;
    $c=curl_init($base.$endpoint);
    curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($dados),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
    $body=curl_exec($c); $status=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    return [$status,json_decode($body,true,512,JSON_THROW_ON_ERROR)];
}
function checar($ok,$mensagem) { if (!$ok) throw new RuntimeException($mensagem); }
$tokens=[];
try {
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
    $prof=$conn->query("SELECT id_usuario FROM usuario WHERE email='professor.local@academia.test'")->fetch_assoc()['id_usuario'];
    $aluno=$conn->query("SELECT id_usuario FROM usuario WHERE email='aluno.local@academia.test'")->fetch_assoc()['id_usuario'];
    $conn->query("INSERT INTO turma (nome_turma,ano_letivo,id_professor) VALUES ('Teste HTTP temporário','2026',".(int)$prof.")");
    $turma=$conn->insert_id;
    $conn->query('INSERT INTO matricula (id_turma,id_aluno) VALUES ('.(int)$turma.','.(int)$aluno.')');
    [$http,$r]=postTeste('login.php',['email'=>$contas[0]['email'],'senha'=>$contas[0]['senha'],'tipo_perfil'=>'aluno']);
    checar($http===200,'Aluno com múltiplas matrículas não entrou.');
    if (isset($r['token'])) postTeste('logout.php',['token'=>$r['token']]);
    echo "OK: logins de aluno/professor, perfis trocados recusados, coordenação exclusiva do painel, senha errada e múltiplas matrículas.\n";
} finally {
    foreach ($tokens as $token) postTeste('logout.php',['token'=>$token]);
    if (isset($turma)) {
        $conn->query('DELETE FROM matricula WHERE id_turma='.(int)$turma);
        $conn->query('DELETE FROM turma WHERE id_turma='.(int)$turma);
    }
}
