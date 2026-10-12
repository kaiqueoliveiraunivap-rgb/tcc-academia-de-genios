<?php
// Biblioteca interna: nunca funciona como endpoint.
if (!defined('COORD_INTERNO')) { http_response_code(404); exit; }
require_once __DIR__.'/../conexao.php';
require_once __DIR__.'/../perfis.php';

function cq(string $sql, array $args=[]): mysqli_stmt {
    global $conn;
    $s=$conn->prepare($sql);
    if ($args) $s->bind_param(str_repeat('s',count($args)),...$args);
    $s->execute(); return $s;
}
function linhas(string $sql,array $args=[]): array { return cq($sql,$args)->get_result()->fetch_all(MYSQLI_ASSOC); }
function falha(string $mensagem,int $codigo=422): void { throw new DomainException($mensagem,$codigo); }
function campo(array $dados,string $nome,int $max,bool $obrigatorio=true): string {
    $v=$dados[$nome] ?? '';
    if (!is_string($v) || mb_strlen($v)>$max || ($obrigatorio && trim($v)==='')) falha('Confira o campo '.$nome.'.');
    return trim($v);
}
function senhaValida($senha): string {
    if (!is_string($senha) || mb_strlen($senha)<8 || strlen($senha)>72 || str_contains($senha,"\0")) falha('A senha precisa ter pelo menos 8 caracteres e no máximo 72 bytes.');
    return $senha;
}
function contaValida(array $dados,bool $nova=true): array {
    $nome=campo($dados,'nome',100); $email=mb_strtolower(campo($dados,'email',150));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) falha('Informe um e-mail válido.');
    $perfil=$dados['perfil'] ?? '';
    if (!in_array($perfil,['aluno','professor','admin'],true)) falha('Perfil inválido.');
    $senha=$dados['senha'] ?? '';
    if ($nova || $senha!=='') senhaValida($senha);
    return compact('nome','email','perfil','senha');
}
function auditar(int $autor,string $acao,?int $alvo=null): void {
    cq('INSERT INTO coordenacao_auditoria(id_autor,acao,id_alvo) VALUES (?,?,?)',[$autor,$acao,$alvo]);
}
function limitarLogin(string $email): void {
    // Incremento atômico antes de verificar a senha; sem confiar em X-Forwarded-For.
    foreach (['ip:'.($_SERVER['REMOTE_ADDR'] ?? 'local')=>40,'email:'.mb_strtolower($email)=>10] as $valor=>$max) {
        $chave=hash('sha256',$valor);
        cq('INSERT INTO coordenacao_limite(chave,tentativas,inicio) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE tentativas=IF(inicio<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,tentativas+1),inicio=IF(inicio<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),inicio)',[$chave]);
        if ((int)linhas('SELECT tentativas FROM coordenacao_limite WHERE chave=?',[$chave])[0]['tentativas']>$max) falha('Muitas tentativas. Aguarde 15 minutos e tente novamente.',429);
    }
    cq('DELETE FROM coordenacao_limite WHERE inicio<DATE_SUB(NOW(),INTERVAL 1 DAY)');
}
function cookieCoord(string $token,int $expira): void {
    setcookie('academia_coord',$token,['expires'=>$expira,'path'=>'/php_appest/coordenacao/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || configuracao('APP_ENV')==='production','httponly'=>true,'samesite'=>'Strict']);
}
function sessaoCoord(): array {
    $token=$_COOKIE['academia_coord'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) falha('Entre para acessar o painel.',401);
    $hash=hash('sha256',$token);
    $r=linhas("SELECT u.id_usuario,u.nome,u.email,s.csrf,s.token_hash FROM coordenacao_sessao s JOIN usuario u ON u.id_usuario=s.id_usuario WHERE s.token_hash=? AND s.expira_em>NOW() AND s.ultimo_acesso>DATE_SUB(NOW(),INTERVAL 60 MINUTE) AND u.excluido_em IS NULL AND LOWER(u.tipo_perfil) IN ('admin','adm','coordenacao','coordenação')",[$hash]);
    if (!$r) falha('Sua sessão expirou. Entre novamente.',401);
    cq('UPDATE coordenacao_sessao SET ultimo_acesso=NOW() WHERE token_hash=?',[$hash]);
    return $r[0];
}
function revogar(int $id): void {
    cq('DELETE FROM coordenacao_sessao WHERE id_usuario=?',[$id]);
    cq('UPDATE sessao SET ativo=0 WHERE id_usuario=?',[$id]);
}
function pastaLivros(): string {
    $p=configuracao('COORD_STORAGE_DIR',dirname(__DIR__,2).'/.runtime/coordenacao');
    if (!is_dir($p) && !mkdir($p,0700,true) && !is_dir($p)) throw new RuntimeException('Não foi possível criar a pasta privada dos livros.');
    return $p;
}
function caminhoFotoPerfil(int $id): string {
    return pastaLivros().'/perfil-'.$id.'.img';
}
function tipoFotoPerfil(string $arquivo): ?string {
    if (!is_file($arquivo)) return null;
    $tipo=(new finfo(FILEINFO_MIME_TYPE))->file($arquivo);
    return in_array($tipo,['image/jpeg','image/png','image/webp'],true) ? $tipo : null;
}
function iniciarWorker(): void {
    if (configuracao('COORD_WORKER_EXTERNO')==='1') return;
    $worker=dirname(__DIR__).'/tools/coordenacao_worker.php';
    // PHP_BINDIR é o caminho usado na compilação; no XAMPP pode apontar para C:\php.
    // PHP_BINARY funciona no servidor local; no Apache, localizamos o CLI ao lado do php.ini.
    $nome=PHP_OS_FAMILY==='Windows' ? 'php.exe' : 'php';
    $candidatos=[configuracao('PHP_CLI_BIN')];
    if (in_array(strtolower(basename(PHP_BINARY)),['php','php.exe'],true)) $candidatos[]=PHP_BINARY;
    if (php_ini_loaded_file()) $candidatos[]=dirname(php_ini_loaded_file()).DIRECTORY_SEPARATOR.$nome;
    $candidatos[]=PHP_BINDIR.DIRECTORY_SEPARATOR.$nome;
    $php='';
    foreach ($candidatos as $candidato) if ($candidato && is_file($candidato)) { $php=$candidato; break; }
    if ($php==='') { error_log('Fila de livros: configure PHP_CLI_BIN com o caminho do executável PHP.'); return; }
    if (PHP_OS_FAMILY==='Windows') {
        // O servidor não depende do shell gráfico do Windows para abrir o processo.
        $ps='$p=New-Object System.Diagnostics.ProcessStartInfo; $p.FileName='.
            "'".str_replace("'","''",$php)."'; ".'$p.Arguments='.
            "'".str_replace("'","''",'"'.$worker.'"')."'; ".
            '$p.UseShellExecute=$false; $p.CreateNoWindow=$true; $p.WindowStyle=[System.Diagnostics.ProcessWindowStyle]::Hidden; [System.Diagnostics.Process]::Start($p) | Out-Null';
        $encoded=base64_encode(mb_convert_encoding($ps,'UTF-16LE','UTF-8'));
        $p=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-EncodedCommand',$encoded],[0=>['file','NUL','r'],1=>['file','NUL','w'],2=>['file',pastaLivros().'/worker-start.log','a']],$pipes);
        if (is_resource($p)) proc_close($p);
    } else {
        exec(escapeshellarg($php).' '.escapeshellarg($worker).' > /dev/null 2>&1 &');
    }
}
function turmasValidas($ids): array {
    if (!is_array($ids) || count($ids)>50) falha('Turmas inválidas.');
    $saida=[];
    foreach ($ids as $id) {
        if (!filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) || !linhas('SELECT id_turma FROM turma WHERE id_turma=?',[$id])) falha('Uma das turmas não existe.');
        $saida[]=(int)$id;
    }
    return array_values(array_unique($saida));
}
function salvarConta(array $dados,int $autor): int {
    global $conn;
    $id=(int)($dados['id'] ?? 0); $c=contaValida($dados,!$id);
    $turmas=$c['perfil']==='aluno' ? turmasValidas($dados['turmas'] ?? []) : [];
    if ($id) {
        $u=linhas('SELECT tipo_perfil FROM usuario WHERE id_usuario=? AND excluido_em IS NULL FOR UPDATE',[$id]);
        if (!$u) falha('Usuário não encontrado.',404);
        if (normalizarPerfil($u[0]['tipo_perfil'])!==$c['perfil']) falha('O perfil de uma conta existente não pode ser trocado.');
        cq('UPDATE usuario SET nome=?,email=? WHERE id_usuario=?',[$c['nome'],$c['email'],$id]);
        if ($c['senha']!=='') { cq('UPDATE usuario SET senha=? WHERE id_usuario=?',[password_hash($c['senha'],PASSWORD_DEFAULT),$id]); revogar($id); }
    } else {
        cq('INSERT INTO usuario(nome,email,senha,tipo_perfil) VALUES (?,?,?,?)',[$c['nome'],$c['email'],password_hash($c['senha'],PASSWORD_DEFAULT),$c['perfil']]);
        $id=(int)$conn->insert_id;
    }
    if ($c['perfil']==='aluno') {
        cq('DELETE FROM matricula WHERE id_aluno=?',[$id]);
        foreach ($turmas as $turma) cq('INSERT INTO matricula(id_aluno,id_turma) VALUES (?,?)',[$id,$turma]);
    }
    auditar($autor,'salvar_'.$c['perfil'],$id); return $id;
}
function lerCsv(string $arquivo,string $perfil): array {
    if (!in_array($perfil,['aluno','professor'],true)) falha('CSV permitido somente para alunos e professores.');
    $raw=file_get_contents($arquivo);
    if ($raw===false || strlen($raw)>2*1024*1024 || !mb_check_encoding($raw,'UTF-8')) falha('Envie um CSV UTF-8 de até 2 MB.');
    $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
    $primeira=strtok($raw,"\r\n"); $sep=substr_count((string)$primeira,';')>substr_count((string)$primeira,',') ? ';' : ',';
    $f=fopen('php://temp','w+'); fwrite($f,$raw); rewind($f);
    $cab=fgetcsv($f,0,$sep,'"','');
    if (!$cab || !in_array('nome',$cab,true) || !in_array('email',$cab,true) || !in_array('senha',$cab,true) || count(array_unique($cab))!==count($cab) || array_diff($cab,['nome','email','senha','turmas'])) falha('Cabeçalho esperado: nome;email;senha;turmas. Turmas é opcional.');
    $contas=[]; $erros=[]; $emails=[]; $linha=1;
    while (($row=fgetcsv($f,0,$sep,'"',''))!==false) {
        $linha++; if ($row===[null]) continue;
        if ($linha>501) falha('Importe no máximo 500 usuários por arquivo.');
        try {
            if (count($row)!==count($cab)) falha('Quantidade de colunas incorreta.');
            $d=array_combine($cab,$row); $d['perfil']=$perfil; $c=contaValida($d);
            if (isset($emails[$c['email']]) || linhas('SELECT id_usuario FROM usuario WHERE email=?',[$c['email']])) falha('E-mail já cadastrado ou repetido no arquivo.');
            $emails[$c['email']]=true;
            $c['turmas']=$perfil==='aluno' ? turmasValidas(empty($d['turmas']) ? [] : explode('|',$d['turmas'])) : [];
            $contas[]=$c;
        } catch (DomainException $e) { $erros[]='Linha '.$linha.': '.$e->getMessage(); }
    }
    fclose($f);
    if ($erros) falha(implode("\n",array_slice($erros,0,20)));
    if (!$contas) falha('O arquivo não contém usuários.');
    return $contas;
}
