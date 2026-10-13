<?php
define('COORD_INTERNO',true);
require __DIR__.'/interno.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function resposta(array $dados): void { echo json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
$transacao=false;
try {
    $metodo=$_SERVER['REQUEST_METHOD'];
    if (!in_array($metodo,['GET','POST'],true)) falha('Método não permitido.',405);
    $dados=$_POST;
    if ($metodo==='POST' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '','application/json')) {
        $raw=file_get_contents('php://input',false,null,0,1048577);
        if (strlen($raw)>1048576) falha('Requisição muito grande.',413);
        $dados=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($dados)) falha('Dados inválidos.');
    }
    $acao=$metodo==='GET' ? ($_GET['acao'] ?? 'sessao') : ($dados['acao'] ?? '');
    if ($acao==='login' && $metodo==='POST') {
        // Login exige JSON e cabeçalho próprio: um formulário externo não consegue enviá-lo.
        if (($_SERVER['HTTP_X_COORD_REQUEST'] ?? '')!=='1' || !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '','application/json')) falha('Requisição inválida.',403);
        $email=campo($dados,'email',150); $senha=$dados['senha'] ?? '';
        if (!is_string($senha) || strlen($senha)>72) falha('E-mail ou senha incorretos.',401);
        limitarLogin($email);
        $u=linhas('SELECT id_usuario,nome,email,senha,tipo_perfil FROM usuario WHERE email=? AND excluido_em IS NULL',[$email])[0] ?? null;
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $ok=password_verify($senha,$u['senha'] ?? $dummy);
        if (!$ok || !$u || normalizarPerfil($u['tipo_perfil'])!=='admin') falha('E-mail ou senha incorretos.',401);
        if (password_needs_rehash($u['senha'],PASSWORD_DEFAULT)) cq('UPDATE usuario SET senha=? WHERE id_usuario=?',[password_hash($senha,PASSWORD_DEFAULT),$u['id_usuario']]);
        $token=bin2hex(random_bytes(32)); $csrf=bin2hex(random_bytes(32));
        cq('DELETE FROM coordenacao_sessao WHERE expira_em<NOW()');
        // Rotação também ao trocar de conta no mesmo navegador.
        cq('DELETE FROM coordenacao_sessao WHERE token_hash=?',[hash('sha256',(string)($_COOKIE['academia_coord'] ?? ''))]);
        cq('INSERT INTO coordenacao_sessao(token_hash,id_usuario,csrf,expira_em,ultimo_acesso) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 8 HOUR),NOW())',[hash('sha256',$token),$u['id_usuario'],$csrf]);
        cookieCoord($token,time()+28800); auditar((int)$u['id_usuario'],'login');
        resposta(['usuario'=>array_intersect_key($u,array_flip(['id_usuario','nome','email']))+['foto'=>tipoFotoPerfil(caminhoFotoPerfil((int)$u['id_usuario']))!==null],'csrf'=>$csrf]);
    }
    $sessao=sessaoCoord(); $autor=(int)$sessao['id_usuario'];
    if ($metodo==='GET') {
        if ($acao==='sessao') resposta(['usuario'=>array_intersect_key($sessao,array_flip(['id_usuario','nome','email']))+['foto'=>tipoFotoPerfil(caminhoFotoPerfil($autor))!==null],'csrf'=>$sessao['csrf']]);
        if ($acao==='minha_foto') {
            $arquivo=caminhoFotoPerfil($autor); $tipo=tipoFotoPerfil($arquivo);
            if ($tipo===null) falha('Foto não encontrada.',404);
            header('Content-Type: '.$tipo); header('Content-Length: '.filesize($arquivo));
            readfile($arquivo); exit;
        }
        if ($acao==='inicio') resposta([
            'contagens'=>linhas('SELECT LOWER(tipo_perfil) AS perfil,COUNT(*) AS total FROM usuario WHERE excluido_em IS NULL GROUP BY LOWER(tipo_perfil)'),
            'livros'=>linhas('SELECT COUNT(*) AS total FROM livro_didatico WHERE ativo=1')[0]['total'],
            'turmas'=>linhas('SELECT COUNT(*) AS total FROM turma')[0]['total'],
            'pendentes'=>linhas("SELECT COUNT(*) AS total FROM coordenacao_livro WHERE estado<>'pronto'")[0]['total']
        ]);
        if ($acao==='usuarios') {
            $perfil=$_GET['perfil'] ?? 'aluno'; if (!in_array($perfil,['aluno','professor','admin'],true)) falha('Perfil inválido.');
            $q='%'.campo($_GET,'q',100,false).'%'; $offset=max(0,(int)($_GET['pagina'] ?? 0))*30;
            $cond=$perfil==='admin' ? "LOWER(u.tipo_perfil) IN ('admin','adm','coordenacao','coordenação')" : 'LOWER(u.tipo_perfil)=?';
            $args=$perfil==='admin' ? [$q,$q] : [$perfil,$q,$q];
            $where="FROM usuario u WHERE u.excluido_em IS NULL AND $cond AND (u.nome LIKE ? OR u.email LIKE ?)";
            resposta(['total'=>linhas('SELECT COUNT(*) AS n '.$where,$args)[0]['n'],'itens'=>linhas("SELECT u.id_usuario,u.nome,u.email,LOWER(u.tipo_perfil) AS perfil,(SELECT GROUP_CONCAT(m.id_turma) FROM matricula m WHERE m.id_aluno=u.id_usuario) AS turmas $where ORDER BY u.nome,u.id_usuario LIMIT 30 OFFSET $offset",$args)]);
        }
        if ($acao==='turmas') {
            $itens=linhas('SELECT t.id_turma,t.nome_turma,t.serie,t.ano_letivo,t.id_professor,u.nome AS professor FROM turma t JOIN usuario u ON u.id_usuario=t.id_professor ORDER BY t.ano_letivo DESC,t.serie,t.nome_turma');
            foreach ($itens as &$turma) {
                preg_match('/(?:^|\s)([A-Z])$/u',trim($turma['nome_turma']),$letra);
                $turma['letra']=$letra[1] ?? '';
            }
            unset($turma);
            resposta(['itens'=>$itens,'professores'=>linhas("SELECT id_usuario,nome FROM usuario WHERE LOWER(tipo_perfil)='professor' AND excluido_em IS NULL ORDER BY nome")]);
        }
        if ($acao==='livros') {
            $pendentes=linhas("SELECT id,titulo,materia,estado,mensagem,criado_em FROM coordenacao_livro WHERE estado<>'pronto' ORDER BY id DESC LIMIT 100");
            foreach ($pendentes as $p) if (in_array($p['estado'],['fila','processando'],true)) {
                $workerAtivo=linhas('SELECT IS_USED_LOCK(?) AS dono',['coord-worker-'.configuracao('DB_NAME','appest')])[0]['dono'];
                if ($workerAtivo===null) iniciarWorker();
                break;
            }
            resposta(['itens'=>linhas('SELECT l.id_livro,l.titulo,l.materia,l.ativo,(SELECT COUNT(*) FROM frente_livro f WHERE f.id_livro=l.id_livro) AS frentes,(SELECT COUNT(*) FROM capitulo_livro c WHERE c.id_livro=l.id_livro AND c.revisado=1) AS capitulos FROM livro_didatico l ORDER BY l.materia,l.titulo'),'pendentes'=>$pendentes]);
        }
        if ($acao==='capitulos') resposta(['itens'=>linhas('SELECT f.titulo AS frente,c.titulo,c.revisado FROM capitulo_livro c JOIN frente_livro f ON f.id_frente=c.id_frente WHERE c.id_livro=? ORDER BY f.ordem,c.ordem',[(int)($_GET['id'] ?? 0)])]);
        if ($acao==='conteudos') resposta([
            'itens'=>linhas('SELECT cp.id_conteudo,cp.nome,cp.serie,cp.materia,cp.bimestre,cp.ordem,cp.ativo,
                GROUP_CONCAT(cc.id_capitulo ORDER BY cc.ordem SEPARATOR ",") AS capitulos
                FROM conteudo_pedagogico cp LEFT JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
                GROUP BY cp.id_conteudo ORDER BY cp.serie,cp.materia,cp.bimestre,cp.ordem,cp.nome'),
            'series'=>linhas("SELECT DISTINCT serie FROM turma WHERE serie IS NOT NULL AND TRIM(serie)<>'' ORDER BY serie"),
            'capitulos'=>linhas('SELECT c.id_capitulo,c.titulo,c.id_livro,l.titulo AS livro,l.materia,
                (SELECT COUNT(*) FROM conteudo_capitulo cc WHERE cc.id_capitulo=c.id_capitulo) AS associacoes
                FROM capitulo_livro c JOIN livro_didatico l ON l.id_livro=c.id_livro
                WHERE c.revisado=1 AND l.ativo=1 ORDER BY l.materia,l.titulo,c.ordem,c.id_capitulo')
        ]);
        falha('Recurso não encontrado.',404);
    }
    if (!hash_equals($sessao['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) falha('Atualize a página antes de continuar.',403);
    if ($acao==='logout') {
        cq('DELETE FROM coordenacao_sessao WHERE token_hash=?',[$sessao['token_hash']]); cookieCoord('',time()-3600); resposta(['ok'=>true]);
    }
    if ($acao==='foto_perfil') {
        $foto=$_FILES['foto'] ?? null;
        if (!is_array($foto) || ($foto['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) falha('Selecione uma foto válida.');
        if ($foto['size']<1 || $foto['size']>2097152 || !is_uploaded_file($foto['tmp_name'])) falha('A foto deve ter até 2 MB.');
        $tipo=(new finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']);
        $dim=@getimagesize($foto['tmp_name']);
        if (!in_array($tipo,['image/jpeg','image/png','image/webp'],true) || !$dim || $dim[0]<1 || $dim[1]<1 || $dim[0]>2048 || $dim[1]>2048) falha('Use uma foto JPG, PNG ou WebP de até 2048 × 2048 pixels.');
        if (!move_uploaded_file($foto['tmp_name'],caminhoFotoPerfil($autor))) throw new RuntimeException('Não foi possível salvar a foto.');
        auditar($autor,'foto_perfil',$autor); resposta(['ok'=>true]);
    }
    if ($acao==='remover_foto') {
        $arquivo=caminhoFotoPerfil($autor);
        if (is_file($arquivo) && !unlink($arquivo)) throw new RuntimeException('Não foi possível remover a foto.');
        auditar($autor,'remover_foto',$autor); resposta(['ok'=>true]);
    }
    // Serializa alterações administrativas; impede exclusões concorrentes dos últimos administradores.
    $lock='coord-'.configuracao('DB_NAME','appest');
    if ((int)linhas('SELECT GET_LOCK(?,10) AS ok',[$lock])[0]['ok']!==1) falha('Outra alteração está sendo concluída. Tente novamente.',409);
    // A conta pode ter sido excluída por outro administrador enquanto aguardava o lock.
    sessaoCoord();
    $conn->begin_transaction(); $transacao=true;
    if ($acao==='salvar_usuario') {
        if (($dados['perfil'] ?? '')==='admin') {
            $hash=linhas('SELECT senha FROM usuario WHERE id_usuario=?',[$autor])[0]['senha'];
            if (!password_verify((string)($dados['senha_atual'] ?? ''),$hash)) falha('Confirme sua senha atual para gerenciar a equipe.',403);
        }
        $id=salvarConta($dados,$autor); $resultado=['ok'=>true,'id'=>$id,'relogin'=>$id===$autor && ($dados['senha'] ?? '')!==''];
    } elseif ($acao==='excluir_usuario') {
        $id=(int)($dados['id'] ?? 0);
        $u=linhas('SELECT tipo_perfil FROM usuario WHERE id_usuario=? AND excluido_em IS NULL FOR UPDATE',[$id])[0] ?? null;
        if (!$u) falha('Usuário não encontrado.',404);
        if ($id===$autor) falha('Você não pode excluir sua própria conta.');
        if (normalizarPerfil($u['tipo_perfil'])==='admin') {
            $hash=linhas('SELECT senha FROM usuario WHERE id_usuario=?',[$autor])[0]['senha'];
            if (!password_verify((string)($dados['senha_atual'] ?? ''),$hash)) falha('Confirme sua senha atual.',403);
            if ((int)linhas("SELECT COUNT(*) AS n FROM usuario WHERE excluido_em IS NULL AND LOWER(tipo_perfil) IN ('admin','adm','coordenacao','coordenação')")[0]['n']<=1) falha('A escola precisa manter uma conta da coordenação.');
        }
        if (normalizarPerfil($u['tipo_perfil'])==='professor' && linhas('SELECT id_turma FROM turma WHERE id_professor=? LIMIT 1',[$id])) falha('Transfira as turmas deste professor na seção Turmas antes de excluí-lo.');
        cq('UPDATE usuario SET excluido_em=NOW() WHERE id_usuario=?',[$id]); revogar($id);
        cq('DELETE FROM matricula WHERE id_aluno=?',[$id]); auditar($autor,'excluir_usuario',$id); $resultado=['ok'=>true];
    } elseif ($acao==='csv') {
        $f=$_FILES['csv'] ?? []; if (($f['error'] ?? 4)!==UPLOAD_ERR_OK) falha('Selecione um CSV de até 2 MB.');
        $contas=lerCsv($f['tmp_name'],(string)($dados['perfil'] ?? ''));
        $confirmar=($dados['confirmar'] ?? '')==='1';
        if ($confirmar) foreach ($contas as $c) salvarConta($c,$autor);
        $resultado=['ok'=>true,'total'=>count($contas),'importado'=>$confirmar,'previa'=>array_map(fn($c)=>array_intersect_key($c,array_flip(['nome','email','perfil','turmas'])),array_slice($contas,0,10))];
    } elseif ($acao==='salvar_turma') {
        $serie=campo($dados,'serie',40); $ano=campo($dados,'ano_letivo',10); $prof=(int)($dados['id_professor'] ?? 0); $id=(int)($dados['id'] ?? 0);
        $letra=mb_strtoupper(trim((string)($dados['letra'] ?? '')));
        // Formulários antigos ainda enviam nome_turma; recupera só a letra.
        if ($letra==='' && preg_match('/(?:^|\s)([A-Z])$/u',trim((string)($dados['nome_turma'] ?? '')),$antiga)) $letra=$antiga[1];
        if (!preg_match('/^[A-Z]$/D',$letra)) falha('Escolha uma letra de turma entre A e Z.');
        $nome=$serie.' '.$letra;
        if (mb_strlen($nome)>80) falha('O nome da turma ficou muito longo.');
        if (!linhas("SELECT id_usuario FROM usuario WHERE id_usuario=? AND LOWER(tipo_perfil)='professor' AND excluido_em IS NULL",[$prof])) falha('Selecione um professor ativo.');
        if ($id) {
            if (!linhas('SELECT id_turma FROM turma WHERE id_turma=?',[$id])) falha('Turma não encontrada.',404);
            cq('UPDATE turma SET nome_turma=?,serie=?,ano_letivo=?,id_professor=? WHERE id_turma=?',[$nome,$serie,$ano,$prof,$id]);
        } else { cq('INSERT INTO turma(nome_turma,serie,ano_letivo,id_professor) VALUES (?,?,?,?)',[$nome,$serie,$ano,$prof]); $id=(int)$conn->insert_id; }
        auditar($autor,'salvar_turma',$id); $resultado=['ok'=>true];
    } elseif ($acao==='salvar_conteudo') {
        $id=(int)($dados['id'] ?? 0);
        $nome=campo($dados,'nome',180); $serie=campo($dados,'serie',80);
        $materia=campo($dados,'materia',80); $ordem=filter_var($dados['ordem'] ?? 0,FILTER_VALIDATE_INT);
        $bimestre=($dados['bimestre'] ?? '')==='' ? null : filter_var($dados['bimestre'],FILTER_VALIDATE_INT);
        $ativo=($dados['ativo'] ?? true) ? 1 : 0;
        $capitulos=$dados['capitulos'] ?? [];
        if ($ordem===false || $ordem<0 || $ordem>10000) falha('Ordem inválida.');
        if ($bimestre!==null && ($bimestre===false || $bimestre<1 || $bimestre>4)) falha('Bimestre inválido.');
        if (!is_array($capitulos) || !$capitulos || count($capitulos)>50) falha('Selecione ao menos um capítulo revisado.');
        $capitulos=array_values(array_unique(array_map(static function($v) {
            $id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$id) falha('Capítulo inválido.');
            return $id;
        },$capitulos)));
        if (!linhas('SELECT 1 FROM turma WHERE serie=? LIMIT 1',[$serie])) falha('Cadastre uma turma com esta série antes de publicar conteúdos.');
        foreach ($capitulos as $idCapitulo) {
            if (!linhas('SELECT 1 FROM capitulo_livro c JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE c.id_capitulo=? AND c.revisado=1 AND l.ativo=1 AND l.materia=?',[$idCapitulo,$materia])) falha('Um capítulo não pertence à matéria ou ainda não está revisado.');
        }
        if ($id) {
            $anterior=linhas('SELECT serie,materia FROM conteudo_pedagogico WHERE id_conteudo=? FOR UPDATE',[$id])[0] ?? null;
            if (!$anterior) falha('Conteúdo não encontrado.',404);
            $atuais=array_map('intval',array_column(linhas('SELECT id_capitulo FROM conteudo_capitulo WHERE id_conteudo=? ORDER BY ordem',[$id]),'id_capitulo'));
            $possuiHistorico=linhas('SELECT 1 FROM quiz_estudo WHERE id_conteudo=? LIMIT 1',[$id])
                || linhas('SELECT 1 FROM progresso_conteudo WHERE id_conteudo=? LIMIT 1',[$id])
                || linhas('SELECT 1 FROM atividade_publicada WHERE id_conteudo=? LIMIT 1',[$id]);
            if ($possuiHistorico && ($anterior['serie']!==$serie || $anterior['materia']!==$materia || $atuais!==$capitulos)) {
                falha('Este conteúdo já possui estudos ou atividades. Preserve série, matéria e capítulos para manter o histórico correto.',409);
            }
            cq('UPDATE conteudo_pedagogico SET nome=?,serie=?,materia=?,bimestre=?,ordem=?,ativo=? WHERE id_conteudo=?',[$nome,$serie,$materia,$bimestre,$ordem,$ativo,$id]);
            cq('DELETE FROM conteudo_capitulo WHERE id_conteudo=?',[$id]);
        } else {
            cq('INSERT INTO conteudo_pedagogico(nome,serie,materia,bimestre,ordem,ativo) VALUES (?,?,?,?,?,?)',[$nome,$serie,$materia,$bimestre,$ordem,$ativo]);
            $id=(int)$conn->insert_id;
        }
        foreach ($capitulos as $posicao=>$idCapitulo) cq('INSERT INTO conteudo_capitulo(id_conteudo,id_capitulo,ordem) VALUES (?,?,?)',[$id,$idCapitulo,$posicao]);
        auditar($autor,'salvar_conteudo',$id); $resultado=['ok'=>true,'id'=>$id];
    } elseif ($acao==='livro_ativo') {
        $id=(int)($dados['id'] ?? 0); $ativo=($dados['ativo'] ?? false) ? 1 : 0;
        if (!linhas('SELECT id_livro FROM livro_didatico WHERE id_livro=?',[$id])) falha('Livro não encontrado.',404);
        cq('UPDATE livro_didatico SET ativo=? WHERE id_livro=?',[$ativo,$id]); auditar($autor,'livro_ativo',$id); $resultado=['ok'=>true];
    } elseif ($acao==='enviar_livro') {
        $titulo=campo($dados,'titulo',255); $materia=campo($dados,'materia',80); $f=$_FILES['pdf'] ?? [];
        if (($f['error'] ?? 4)!==UPLOAD_ERR_OK || ($f['size'] ?? 0)>128*1024*1024) falha('Selecione um PDF de até 128 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if ($mime!=='application/pdf' || file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') falha('O arquivo enviado não é um PDF válido.');
        $sha=hash_file('sha256',$f['tmp_name']);
        if (linhas('SELECT id FROM coordenacao_livro WHERE sha256=?',[$sha])) falha('Este PDF já foi enviado. Consulte seu processamento na biblioteca.',409);
        $arquivo=bin2hex(random_bytes(32)); $destino=pastaLivros().'/'.$arquivo.'.pdf';
        if (!move_uploaded_file($f['tmp_name'],$destino)) throw new RuntimeException('Falha ao armazenar PDF.');
        try { cq('INSERT INTO coordenacao_livro(arquivo,sha256,titulo,materia,id_autor) VALUES (?,?,?,?,?)',[$arquivo,$sha,$titulo,$materia,$autor]); }
        catch (Throwable $e) { unlink($destino); throw $e; }
        $id=(int)$conn->insert_id; auditar($autor,'enviar_livro',$id); $resultado=['ok'=>true,'id'=>$id];
    } elseif ($acao==='reprocessar_livro') {
        $id=(int)($dados['id'] ?? 0); $job=linhas("SELECT arquivo FROM coordenacao_livro WHERE id=? AND estado='revisao'",[$id])[0] ?? null;
        if (!$job) falha('Importação não está aguardando revisão.',409);
        $mapa=$dados['mapa'] ?? [];
        if (!is_array($mapa) || !$mapa || count($mapa)>500) falha('Informe os capítulos e suas páginas.');
        $anterior=0;
        foreach ($mapa as &$cap) {
            $cap=['frente'=>campo($cap,'frente',180),'titulo'=>campo($cap,'titulo',180),'inicio'=>(int)($cap['inicio'] ?? 0),'fim'=>(int)($cap['fim'] ?? 0)];
            if ($cap['inicio']<1 || $cap['inicio']<=$anterior || $cap['fim']<$cap['inicio'] || $cap['fim']>10000) falha('Use páginas físicas em ordem, sem sobreposição.');
            $anterior=$cap['fim'];
        } unset($cap);
        if (file_put_contents(pastaLivros().'/'.$job['arquivo'].'.mapa.json',json_encode($mapa,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Falha ao salvar revisão.');
        cq("UPDATE coordenacao_livro SET estado='fila',mensagem='' WHERE id=?",[$id]); auditar($autor,'revisar_livro',$id); $resultado=['ok'=>true];
    } else { falha('Ação não encontrada.',404); }
    $conn->commit(); $transacao=false;
    cq('SELECT RELEASE_LOCK(?)',[$lock]);
    if (in_array($acao,['enviar_livro','reprocessar_livro'],true)) iniciarWorker();
    resposta($resultado);
} catch (Throwable $e) {
    if ($transacao) $conn->rollback();
    $codigo=$e instanceof DomainException ? $e->getCode() : 500;
    $mensagem=$e instanceof DomainException ? $e->getMessage() : 'Não foi possível concluir. Tente novamente.';
    if ($e instanceof mysqli_sql_exception && $e->getCode()===1062) { $codigo=409; $mensagem='Este e-mail ou arquivo já está cadastrado. Nenhuma alteração foi salva.'; }
    if ($e instanceof JsonException) { $codigo=422; $mensagem='Dados inválidos.'; }
    if ($codigo===500) error_log('Coordenação: '.$e->getMessage());
    http_response_code($codigo ?: 422); resposta(['mensagem'=>$mensagem]);
}
