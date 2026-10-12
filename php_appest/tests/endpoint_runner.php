<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$endpoint=$argv[1] ?? '';
if (!in_array($endpoint,['enviar_prompt_professor.php','buscar_prompt_pendente.php','gerar_estudo.php','concluir_estudo.php','historico_estudos.php','revisar_estudo.php','listar_capitulos.php','listar_frentes.php','ranking_serie.php','desempenho_estudos.php','listar_livros_professor.php','inserir_usuario.php','cadastrar_professor.php','dashboard_turmas.php'],true)) exit(1);
$_POST=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_METHOD']='POST';
register_shutdown_function(function(){ fwrite(STDERR,'HTTP_STATUS='.(http_response_code() ?: 200)); });
require __DIR__.'/../'.$endpoint;
