<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/estudo_formato.php';
require_once __DIR__ . '/orientacao_turma.php';
require_once __DIR__ . '/perfis.php';
$aluno=exigirUsuarioLogado($conn);
if (normalizarPerfil($aluno['tipo_perfil'])!=='aluno') responderErro('Estudo exclusivo de alunos.',403);
set_time_limit(210);
$id = inteiroDoPost('id_capitulo');
$idConteudo=inteiroDoPost('id_conteudo');
if (isset($_POST['id_conteudo']) && !$idConteudo) responderErro('Conteúdo inválido.',422);
$nivel = 'MEDIO';
if (!$id) responderErro('Capítulo inválido.');
$idTurma=turmaDoEstudo($conn,$aluno['id_usuario'],inteiroDoPost('id_turma'));
$lock = false;
$inicioRequisicao=microtime(true);
$origemGeracao='cache';

function registrarMetricasGeracaoEstudo(array $dados): void {
    // Não registra capítulo, token de sessão, chave ou conteúdo didático.
    if (configuracao('APP_ENV','development')==='production') return;
    error_log('[gerar_estudo] '.json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function motivoSeguroRetryEstudo(Throwable $erro): string {
    $mensagem=$erro->getMessage();
    if (str_contains($mensagem,'Referência não encontrada')) return 'referencia_invalida';
    if (str_contains($mensagem,'Explicação pouco desenvolvida')) return 'explicacao_curta';
    if (str_contains($mensagem,'Questão')) return 'questao_invalida';
    if (str_contains($mensagem,'Geração incompleta')) return 'geracao_incompleta';
    if ($erro instanceof JsonException) return 'json_invalido';
    return 'falha_'.(new ReflectionClass($erro))->getShortName();
}
try {
    $stmt=$conn->prepare('SELECT c.*, l.titulo AS livro, l.materia, l.fonte_url FROM capitulo_livro c JOIN livro_didatico l ON l.id_livro=c.id_livro WHERE c.id_capitulo=? AND c.revisado=1 AND l.ativo=1');
    $stmt->bind_param('i',$id); $stmt->execute();
    $cap=$stmt->get_result()->fetch_assoc();
    if (!$cap) responderErro('Capítulo não disponível para estudo.',404);
    if ($idConteudo) {
        if (!$idTurma) responderErro('Informe sua turma para abrir este conteúdo.',422);
        $s=$conn->prepare('SELECT 1 FROM conteudo_pedagogico cp
            JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo AND cc.id_capitulo=?
            JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
            JOIN matricula m ON m.id_turma=t.id_turma AND m.id_aluno=?
            WHERE cp.id_conteudo=? AND cp.materia=? AND cp.ativo=1 LIMIT 1');
        $s->bind_param('iiiis',$id,$idTurma,$aluno['id_usuario'],$idConteudo,$cap['materia']);
        $s->execute(); $permitido=$s->get_result()->num_rows===1; $s->close();
        if (!$permitido) responderErro('Conteúdo indisponível para sua série.',403);
    } elseif ($idTurma) {
        $s=$conn->prepare('SELECT cp.id_conteudo FROM conteudo_pedagogico cp
            JOIN conteudo_capitulo cc ON cc.id_conteudo=cp.id_conteudo
            JOIN turma t ON t.serie=cp.serie AND t.id_turma=?
            WHERE cc.id_capitulo=? AND cp.ativo=1 LIMIT 2');
        $s->bind_param('ii',$idTurma,$id); $s->execute(); $itens=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        if (count($itens)>1) responderErro('Selecione o conteúdo no aplicativo atualizado.',422);
        if (count($itens)===1) $idConteudo=(int)$itens[0]['id_conteudo'];
    }
    if (!$idConteudo) {
        $s=$conn->prepare('SELECT 1 FROM conteudo_capitulo cc JOIN conteudo_pedagogico cp ON cp.id_conteudo=cc.id_conteudo
            WHERE cc.id_capitulo=? LIMIT 1');
        $s->bind_param('i',$id); $s->execute(); $classificado=$s->get_result()->num_rows>0; $s->close();
        if ($classificado) responderErro('Conteúdo indisponível para sua série.',403);
    }
    $paginas=json_decode($cap['paginas_json'],true,512,JSON_THROW_ON_ERROR);
    // Nunca truncar o capítulo silenciosamente.
    if (!$paginas || strlen($cap['paginas_json'])>180000) responderErro('Capítulo sem texto ou muito extenso. Solicite a revisão/divisão do conteúdo.',422);
    $model=configuracao('GEMINI_MODEL');
    if (!$model || !preg_match('/^[a-zA-Z0-9.\-]+$/',$model)) responderErro('Configure GEMINI_MODEL no servidor.',503);
    $orientacao=orientacaoDaTurma($conn,$idTurma,$cap['materia'],(int)$cap['id_livro'],$idConteudo);
    $idPrompt=$orientacao ? (int)$orientacao['id_prompt'] : null;
    // Nova explicação, edição do capítulo ou orientação exigem um novo estudo.
    // O resultado personalizado é compartilhado somente pela turma destinatária.
    $versao=versaoEstudoCompleto($model,$cap['paginas_json'],$idPrompt);
    $lockName='estudo:'.hash('sha256',$id.'|'.$versao);
    $lockName=substr($lockName,0,64);
    $s=$conn->prepare('SELECT id_estudo,conteudo_json FROM estudo_gerado WHERE id_capitulo=? AND dificuldade=? AND versao_gerador=?');
    $s->bind_param('iss',$id,$nivel,$versao); $s->execute(); $cache=$s->get_result()->fetch_assoc();
    if (!$cache) {
        // Vários alunos podem pedir o mesmo capítulo ao mesmo tempo. Quem chega
        // depois aguarda a primeira geração e reutiliza o resultado validado.
        $inicioEspera=microtime(true);
        $s=$conn->prepare('SELECT GET_LOCK(?, 45) AS adquirido'); $s->bind_param('s',$lockName); $s->execute();
        $lock=(int)$s->get_result()->fetch_assoc()['adquirido']===1;
        if (!$lock) responderErro('Este estudo está sendo preparado. Tente novamente em instantes.',409);
        $s=$conn->prepare('SELECT id_estudo,conteudo_json FROM estudo_gerado WHERE id_capitulo=? AND dificuldade=? AND versao_gerador=?');
        $s->bind_param('iss',$id,$nivel,$versao); $s->execute(); $cache=$s->get_result()->fetch_assoc();
        if (!$cache && microtime(true)-$inicioEspera>5) throw new RuntimeException('A geração simultânea não produziu um estudo válido.');
    }
    if ($cache) {
        $idEstudo=(int)$cache['id_estudo'];
        $dados=json_decode($cache['conteudo_json'],true,512,JSON_THROW_ON_ERROR);
    }
    else {
        $origemGeracao='nova';
        $key=configuracao('GEMINI_API_KEY');
        if (!$key) throw new RuntimeException('Chave da IA não configurada no servidor.');
        $fontesPreparadas=prepararFontesEstudo($paginas);
        $string=['type'=>'STRING'];
        $ref=['referencia'=>['type'=>'STRING','enum'=>array_keys($fontesPreparadas)]];
        $schema=['type'=>'OBJECT','properties'=>[
            'resumo'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>array_merge(['titulo'=>$string,'texto'=>$string,'topicos'=>['type'=>'ARRAY','items'=>$string]],$ref),'required'=>['titulo','texto','topicos','referencia']]],
            'palavras_chave'=>['type'=>'ARRAY','items'=>$string],
            'questoes'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>array_merge(['enunciado'=>$string,'explicacao'=>$string,'alternativas'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>['texto'=>$string,'ehCorreta'=>['type'=>'BOOLEAN']],'required'=>['texto','ehCorreta']]]],$ref),'required'=>['enunciado','explicacao','alternativas','referencia']]]
        ],'required'=>['resumo','questoes','palavras_chave']];
        $minimo=minimoPalavrasExplicacao($paginas);
        $porBloco=max(45,(int)ceil($minimo/8)+15);
        $instrucao=instrucaoEstudoCompleto().' Para este capítulo, organize a explicação em 8 blocos. Escreva de '.$porBloco.' a '.($porBloco+40).' palavras no campo texto de cada bloco, além dos tópicos. O conjunto precisa de no mínimo '.$minimo.' palavras, mantendo cada parágrafo fundamentado no trecho escolhido. Confira esses limites antes de concluir o JSON; não envie uma síntese curta.';
        $entrada='Matéria: '.$cap['materia']."\nCapítulo selecionado: ".$cap['titulo']."\nTexto completo do capítulo em trechos:\n".json_encode($fontesPreparadas,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if ($orientacao) $entrada.="\n\nOrientação do professor para este estudo: ".mb_substr($orientacao['prompt_final'],0,4000)."\nAplique o foco no resumo e crie questões de quiz sobre ele, sempre com referências ao capítulo. Se o trecho não sustentar algum ponto, não invente fatos.";
        $body=['systemInstruction'=>['parts'=>[['text'=>$instrucao]]],'contents'=>[['role'=>'user','parts'=>[['text'=>$entrada]]]],'generationConfig'=>['temperature'=>0.2,'maxOutputTokens'=>5000,'responseMimeType'=>'application/json','responseSchema'=>$schema]];
        $falha=null;
        $tentativas=0;
        $usoGemini=[];
        $motivoRetry=null;
        for ($tentativa=0; $tentativa<2; $tentativa++) {
            $tentativas++;
            if ($tentativa>0) usleep(800000);
            // O retry reenvia apenas a fonte original e a causa curta. Incluir a
            // resposta inválida anterior dobrava o input e não ajudava a validá-la.
            $corpoTentativa=$body;
            if ($tentativa>0) $corpoTentativa['contents'][0]['parts'][0]['text']=$entrada."\n\nCorreção obrigatória: ".mb_substr($falha->getMessage(),0,280)." Retorne o JSON completo, com 8 blocos de ao menos {$porBloco} palavras e todas as regras acima.";
            try {
                $ch=curl_init('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent');
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$key],CURLOPT_POSTFIELDS=>json_encode($corpoTentativa,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
                $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlError=curl_error($ch); curl_close($ch);
                if ($http!==200 || !$raw) {
                    $provider=json_decode($raw ?: '{}',true);
                    $providerError=$provider['error']['status'] ?? '';
                    $providerMessage=mb_substr((string)($provider['error']['message'] ?? ''),0,300);
                    $falha=new RuntimeException('Provedor indisponível (HTTP '.$http.', '.($providerError ?: $curlError).'; '.$providerMessage.').');
                    if ($http>=400 && $http<500 && $http!==429) throw $falha;
                    throw $falha;
                }
                $res=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
                $candidate=$res['candidates'][0] ?? [];
                if (($candidate['finishReason'] ?? '')!=='STOP') throw new RuntimeException('Geração incompleta.');
                $text=''; foreach ($candidate['content']['parts'] ?? [] as $part) if (empty($part['thought'])) $text.=$part['text'] ?? '';
                $usoGemini=$res['usageMetadata'] ?? [];
                $dados=validarEstudoCompleto(resolverReferenciasEstudo(json_decode($text,true,512,JSON_THROW_ON_ERROR),$fontesPreparadas),$paginas);
                $falha=null;
                break;
            } catch (Throwable $e) {
                $falha=$e;
                $motivoRetry=motivoSeguroRetryEstudo($e);
                if (isset($http) && $http>=400 && $http<500 && $http!==429) break;
            }
        }
        if ($falha) throw $falha;
        $json=json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $turmaDestinataria=$idPrompt ? $idTurma : null;
        $s=$conn->prepare('INSERT INTO estudo_gerado (id_capitulo,dificuldade,versao_gerador,conteudo_json,id_turma_destinataria) VALUES (?,?,?,?,?)');
        $s->bind_param('isssi',$id,$nivel,$versao,$json,$turmaDestinataria); $s->execute();
        $idEstudo=(int)$conn->insert_id;
        registrarMetricasGeracaoEstudo(['origem'=>$origemGeracao,'duracao_ms'=>(int)((microtime(true)-$inicioRequisicao)*1000),'entrada_caracteres'=>mb_strlen($entrada),'saida_caracteres'=>mb_strlen($json),'modelo'=>$model,'tentativas'=>$tentativas,'retry'=>$tentativas>1,'motivo_retry'=>$tentativas>1 ? $motivoRetry : null,'uso'=>$usoGemini]);
    }
    validarEstudoCompleto($dados,$paginas);
    if ($origemGeracao==='cache') registrarMetricasGeracaoEstudo(['origem'=>'cache','duracao_ms'=>(int)((microtime(true)-$inicioRequisicao)*1000),'modelo'=>$model]);
    $resposta=['status'=>'sucesso','id_estudo'=>$idEstudo,'id_turma'=>$idTurma,'dificuldade'=>$nivel,'estudo'=>$dados,'livro'=>$cap['livro'],'capitulo'=>$cap['titulo'],'fonte_url'=>$cap['fonte_url'],
        'bimestre'=>$orientacao['bimestre'] ?? null,'orientacao_professor'=>$orientacao ? ['professor'=>$orientacao['nome_professor'],'instrucao'=>$orientacao['instrucao']] : null];
} catch (Throwable $e) {
    error_log('Falha estudo: '.get_class($e).' — '.$e->getMessage());
    $mensagem=str_contains($e->getMessage(),'HTTP 0,')
        ? 'O servidor não conseguiu se conectar à IA. Verifique a conexão com a internet e tente novamente.'
        : 'Não foi possível gerar um estudo validado com este capítulo. Tente novamente em instantes.';
    $resposta=['status'=>'erro','mensagem'=>$mensagem];
    $codigo=503;
} finally {
    if ($lock) { $s=$conn->prepare('SELECT RELEASE_LOCK(?)'); $s->bind_param('s',$lockName); $s->execute(); }
}
responderJson($resposta,$codigo ?? 200);
