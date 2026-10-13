<?php
// Instalador idempotente para desenvolvimento e implantação. Não tem acesso HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = configuracao('DB_NAME','appest');
    if (!preg_match('/^[A-Za-z0-9_]+$/',$db)) throw new RuntimeException('DB_NAME inválido.');
    $producao=strtolower((string)configuracao('APP_ENV','development'))==='production';
    $conn=new mysqli(configuracao('DB_HOST','127.0.0.1'),configuracao('DB_USER','root'),configuracao('DB_PASSWORD'),$producao ? $db : '',(int)configuracao('DB_PORT',3306));
    $conn->set_charset('utf8mb4');
    if (!$producao) {
        $conn->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($db);
    }
    $lock='academia-instalacao-'.$db;
    $s=$conn->prepare('SELECT GET_LOCK(?,30) AS ok'); $s->bind_param('s',$lock); $s->execute();
    if ((int)$s->get_result()->fetch_assoc()['ok']!==1) throw new RuntimeException('Outra instalação está em andamento.');
    $arquivos=[];
    // appest_schema.sql foi gerado a partir do dump oficial fornecido, sem
    // usuários, senhas, sessões ou históricos. Em banco vazio ele é a base;
    // os scripts seguintes continuam sendo migrações idempotentes.
    if ($conn->query('SHOW TABLES')->num_rows===0) $arquivos[]='appest_schema.sql';
    // O dump inicial precisa existir antes do registro de instalação.
    foreach ($arquivos as $arquivo) {
        $sql=file_get_contents(__DIR__.'/../'.$arquivo);
        $conn->multi_query($sql);
        do { if ($r=$conn->store_result()) $r->free(); } while ($conn->more_results() && $conn->next_result());
    }
    $conn->query('CREATE TABLE IF NOT EXISTS instalacao_local (chave VARCHAR(80) PRIMARY KEY, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    $scripts=['banco_base_referencia.sql','dashboard_desempenho.sql','cadastro_professor.sql','prompt_professor.sql','biblioteca.sql','menu_desempenho.sql'];
    $fontes=array_map(fn($nome)=>__DIR__.'/../'.$nome,$scripts);
    $fontes=array_merge($fontes,[__FILE__,__DIR__.'/../appest_schema.sql',__DIR__.'/migrar_biblioteca.php',__DIR__.'/migrar_menu.php',__DIR__.'/sincronizar_catalogo.php',dirname(__DIR__,2).'/biblioteca/catalogo-drive.json']);
    $fontes[]=__DIR__.'/migrar_coordenacao.php';
    $fontes[]=__DIR__.'/migrar_orientacoes.php';
    $assinatura=hash('sha256',implode('',array_map(fn($arquivo)=>hash_file('sha256',$arquivo),$fontes)));
    $marcador='estrutura-v2-'.$assinatura;
    $s=$conn->prepare('SELECT 1 FROM instalacao_local WHERE chave=?'); $s->bind_param('s',$marcador); $s->execute();
    $estruturaPronta=$s->get_result()->num_rows>0;
    if (!$estruturaPronta) {
        foreach ($scripts as $arquivo) {
            $sql=file_get_contents(__DIR__.'/../'.$arquivo);
            // O instalador controla o nome do banco; o SQL de referência usa appest.
            $sql=preg_replace('/CREATE DATABASE IF NOT EXISTS appest\s+DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;/i','',$sql);
            $sql=preg_replace('/\bUSE appest;/i','',$sql);
            $conn->multi_query($sql);
            do { if ($r=$conn->store_result()) $r->free(); } while ($conn->more_results() && $conn->next_result());
        }
        $tipo=$conn->query("SHOW COLUMNS FROM usuario LIKE 'tipo_perfil'")->fetch_assoc()['Type'];
        if (stripos($tipo,'enum')===0) {
            // Conversão sem perda dos perfis existentes; permite ADMIN.
            $conn->query("ALTER TABLE usuario MODIFY tipo_perfil VARCHAR(20) NOT NULL DEFAULT 'aluno'");
        }
        require __DIR__.'/migrar_biblioteca.php';
        require __DIR__.'/sincronizar_catalogo.php';
    }
    $criada=$conn->query("SELECT chave FROM instalacao_local WHERE chave='contas-desenvolvimento-v1'")->num_rows>0;
    if (!$producao && !$criada) {
        $dir=dirname(__DIR__,2).'/.runtime';
        if (!is_dir($dir)) mkdir($dir,0700,true);
        $arquivo=$dir.'/acessos-locais.json';
        // O arquivo é privado e reutilizado se uma execução for interrompida.
        $contas=is_file($arquivo) ? json_decode(file_get_contents($arquivo),true,512,JSON_THROW_ON_ERROR) : [];
        if (!$contas) {
            foreach (['aluno','professor','admin'] as $p) $contas[]=['perfil'=>$p,'nome'=>'Teste '.ucfirst($p),'email'=>$p.'.local@academia.test','senha'=>bin2hex(random_bytes(8)).'Aa1'];
            if (file_put_contents($arquivo,json_encode($contas,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar os acessos locais.');
        }
        $conn->begin_transaction();
        $ids=[];
        foreach ($contas as $c) {
            $s=$conn->prepare('SELECT id_usuario FROM usuario WHERE email=?'); $s->bind_param('s',$c['email']); $s->execute();
            $existente=$s->get_result()->fetch_assoc();
            if ($existente) throw new RuntimeException('E-mail de desenvolvimento já existe. Nenhuma conta existente foi alterada.');
            $hash=password_hash($c['senha'],PASSWORD_DEFAULT);
            $s=$conn->prepare('INSERT INTO usuario (nome,email,senha,tipo_perfil) VALUES (?,?,?,?)');
            $s->bind_param('ssss',$c['nome'],$c['email'],$hash,$c['perfil']); $s->execute(); $ids[$c['perfil']]=$s->insert_id;
        }
        $ano=date('Y');
        $s=$conn->prepare("INSERT INTO turma (nome_turma,ano_letivo,id_professor) VALUES ('Turma de desenvolvimento',?,?)");
        $s->bind_param('si',$ano,$ids['professor']); $s->execute(); $turma=$s->insert_id;
        $s=$conn->prepare('INSERT INTO matricula (id_aluno,id_turma) VALUES (?,?)'); $s->bind_param('ii',$ids['aluno'],$turma); $s->execute();
        $conn->query("INSERT INTO instalacao_local (chave) VALUES ('contas-desenvolvimento-v1')");
        $conn->commit();
    }
    if (!$estruturaPronta) {
        require __DIR__.'/migrar_menu.php';
        require __DIR__.'/migrar_coordenacao.php';
        require __DIR__.'/migrar_orientacoes.php';
        $s=$conn->prepare('INSERT INTO instalacao_local (chave) VALUES (?)'); $s->bind_param('s',$marcador); $s->execute();
        $conn->query("DELETE FROM instalacao_local WHERE chave LIKE 'estrutura-v2-%' AND chave<>'".$conn->real_escape_string($marcador)."'");
    }
    // Independente da assinatura legada: não força a reaplicação dos scripts
    // antigos quando apenas a organização pedagógica evoluir.
    require __DIR__.'/migrar_pedagogico.php';
    echo $producao
        ? "Banco de produção preparado. Contas existentes preservadas.\n"
        : "Banco preparado. Contas existentes preservadas. Acessos de teste: .runtime/acessos-locais.json\n";
} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    fwrite(STDERR,'Instalação falhou: '.$e->getMessage()."\n"); exit(1);
}
