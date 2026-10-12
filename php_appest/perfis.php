<?php
function normalizarPerfil($valor) {
    if (!is_string($valor)) return null;
    $perfil = strtolower(trim($valor));
    if (in_array($perfil, ['admin', 'adm', 'coordenacao', 'coordenação'], true)) return 'admin';
    return in_array($perfil, ['aluno', 'professor'], true) ? $perfil : null;
}
