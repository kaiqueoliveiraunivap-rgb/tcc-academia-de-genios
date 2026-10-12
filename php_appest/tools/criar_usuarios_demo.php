<?php
// Contas locais de demonstração. Execute por CLI, nunca por HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexao.php';
if (strtolower((string)configuracao('APP_ENV','development')) === 'production') {
    fwrite(STDERR,"Contas de demonstração não são criadas em produção.\n");
    exit(1);
}

$diretorio=dirname(__DIR__,2).'/.runtime';
$arquivo=$diretorio.'/acessos-demonstracao.json';
if (!is_dir($diretorio) && !mkdir($diretorio,0700,true)) throw new RuntimeException('Não foi possível criar o diretório privado.');
$novoArquivo=!is_file($arquivo);

if ($novoArquivo) {
    $contas=[];
    foreach (['aluno'=>10,'professor'=>10,'admin'=>2] as $perfil=>$quantidade) {
        for ($numero=1;$numero<=$quantidade;$numero++) {
            $sufixo=str_pad((string)$numero,2,'0',STR_PAD_LEFT);
            $nomePerfil=$perfil==='admin' ? 'Coordenação' : ucfirst($perfil);
            $contas[]=[
                'perfil'=>$perfil,
                'nome'=>$nomePerfil.' Demonstração '.$sufixo,
                'email'=>$perfil.'.demo'.$sufixo.'@academia.test',
                'senha'=>bin2hex(random_bytes(10)).'Aa1!',
            ];
        }
    }
    $json=json_encode($contas,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if (file_put_contents($arquivo,$json,LOCK_EX)===false) throw new RuntimeException('Não foi possível guardar as senhas privadas.');
} else {
    $contas=json_decode(file_get_contents($arquivo),true,512,JSON_THROW_ON_ERROR);
}
if (!is_array($contas) || count($contas)!==22) throw new RuntimeException('Arquivo de acessos incompleto.');

try {
    $conn->begin_transaction();
    $ids=['aluno'=>[],'professor'=>[],'admin'=>[]];
    $inseridos=0;
    foreach ($contas as $conta) {
        $email=$conta['email'];
        $s=$conn->prepare('SELECT id_usuario,senha,tipo_perfil FROM usuario WHERE email=? LIMIT 1');
        $s->bind_param('s',$email); $s->execute(); $existente=$s->get_result()->fetch_assoc();
        if ($existente) {
            if (strtolower($existente['tipo_perfil'])!==$conta['perfil'] || !password_verify($conta['senha'],$existente['senha'])) {
                throw new RuntimeException('O e-mail '.$email.' já pertence a outra conta. Nada foi alterado.');
            }
            $id=(int)$existente['id_usuario'];
        } else {
            $hash=password_hash($conta['senha'],PASSWORD_DEFAULT);
            $s=$conn->prepare('INSERT INTO usuario (nome,email,senha,tipo_perfil) VALUES (?,?,?,?)');
            $s->bind_param('ssss',$conta['nome'],$email,$hash,$conta['perfil']); $s->execute();
            $id=$conn->insert_id;
            $inseridos++;
        }
        $ids[$conta['perfil']][]=$id;
    }

    // Cada professor recebe uma turma com um aluno, para que ranking e gráficos
    // também possam ser testados com todas as contas recém-criadas.
    $ano=date('Y');
    foreach ($ids['professor'] as $i=>$idProfessor) {
        $nomeTurma='Turma demonstração '.str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
        $s=$conn->prepare('SELECT id_turma FROM turma WHERE id_professor=? AND nome_turma=? AND ano_letivo=? LIMIT 1');
        $s->bind_param('iss',$idProfessor,$nomeTurma,$ano); $s->execute(); $turma=$s->get_result()->fetch_assoc();
        if ($turma) $idTurma=(int)$turma['id_turma'];
        else {
            $serie='1ª série';
            $s=$conn->prepare('INSERT INTO turma (nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)');
            $s->bind_param('sssi',$nomeTurma,$serie,$ano,$idProfessor); $s->execute();
            $idTurma=$conn->insert_id;
        }
        $idAluno=$ids['aluno'][$i];
        $s=$conn->prepare('SELECT 1 FROM matricula WHERE id_turma=? AND id_aluno=? LIMIT 1');
        $s->bind_param('ii',$idTurma,$idAluno); $s->execute();
        if (!$s->get_result()->fetch_assoc()) {
            $s=$conn->prepare('INSERT INTO matricula (id_turma,id_aluno) VALUES (?,?)');
            $s->bind_param('ii',$idTurma,$idAluno); $s->execute();
        }
    }
    $conn->commit();
    echo "Contas de demonstração prontas: 10 alunos, 10 professores e 2 coordenações. Novas contas: $inseridos. Senhas: .runtime/acessos-demonstracao.json\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR,'Falha ao criar contas: '.$e->getMessage()."\n");
    exit(1);
}
