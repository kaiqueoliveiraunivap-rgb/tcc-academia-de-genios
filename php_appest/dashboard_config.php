<?php
// Configuração central do Dashboard de Desempenho das Turmas.
// Mantém em um único lugar as regras de negócio que são usadas por vários
// endpoints (nota de corte, escala das notas e duração da sessão), evitando
// que cada arquivo repita o mesmo número mágico.

// Nota mínima para o aluno ser considerado aprovado.
const MEDIA_APROVACAO = 6.0;

// Escala das notas aceita pelo sistema (usada na validação da edição).
const NOTA_MINIMA = 0.0;
const NOTA_MAXIMA = 10.0;

// Tempo de vida do token de sessão, em horas.
const SESSAO_HORAS = 8;
