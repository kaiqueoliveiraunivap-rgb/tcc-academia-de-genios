<?php
// Dados de alunos e professores são alterados exclusivamente pela secretaria.
require_once 'auth.php';
exigirUsuarioLogado($conn);
responderErro('Para alterar seus dados, solicite atendimento à secretaria.', 403);
