<?php
// Funções de apoio compartilhadas pelos endpoints do dashboard.
// Ficam aqui para que a regra de cálculo (média, % de aprovação, rótulo de
// período) seja idêntica na visão geral e na análise detalhada.

require_once 'dashboard_config.php';

/** Arredonda para 2 casas devolvendo float (o JSON sai como número, não texto). */
function arredondar($valor, $casas = 2)
{
    return round(floatval($valor), $casas);
}

/** Converte "2026-03" em "mar/26" para usar como rótulo dos gráficos. */
function rotuloMes($periodo)
{
    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
              'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    $partes = explode('-', $periodo);
    if (count($partes) !== 2) {
        return $periodo;
    }

    $indice = intval($partes[1]) - 1;
    if ($indice < 0 || $indice > 11) {
        return $periodo;
    }

    return $meses[$indice] . '/' . substr($partes[0], 2);
}

/** Percentual (0..100) de quantos itens de um total, protegido contra divisão por zero. */
function percentual($parte, $total)
{
    if ($total <= 0) {
        return 0.0;
    }
    return arredondar(($parte / $total) * 100, 1);
}

/** Um aluno é aprovado quando a média dele alcança a nota de corte. */
function estaAprovado($media)
{
    return floatval($media) >= MEDIA_APROVACAO;
}
