<?php
// Criação inicial somente no terminal. A senha chega pelo STDIN, nunca por URL/argumento.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('COORD_INTERNO',true);
require __DIR__.'/../coordenacao/interno.php';
try {
    $d=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    $d['perfil']='admin';$d['id']=0;
    $conn->begin_transaction();$id=salvarConta($d,0);$conn->commit();
    echo "Conta da coordenação criada. Entre no painel com o e-mail informado.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR,($e instanceof mysqli_sql_exception && $e->getCode()===1062 ? 'Este e-mail já está cadastrado.' : ($e instanceof DomainException ? $e->getMessage() : 'Não foi possível criar a conta. Confira o banco e os dados.'))."\n");exit(1);
}
