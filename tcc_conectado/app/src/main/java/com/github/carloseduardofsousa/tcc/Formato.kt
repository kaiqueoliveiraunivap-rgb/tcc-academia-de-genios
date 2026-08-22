package com.github.carloseduardofsousa.tcc

import java.util.Locale

/**
 * Formatação dos números do dashboard em português (vírgula decimal).
 * Fica em um único lugar para nota, percentual e variação aparecerem
 * exatamente iguais nos cards, nos gráficos e no modal de edição.
 */
object Formato {

    private val BR: Locale = Locale.forLanguageTag("pt-BR")

    /** 8.7 -> "8,7" */
    fun nota(valor: Double): String = String.format(BR, "%.1f", valor)

    /** 8.75 -> "8,75" (usado no campo de edição, que aceita 2 casas) */
    fun notaPrecisa(valor: Double): String =
        String.format(BR, "%.2f", valor).trimEnd('0').trimEnd(',')

    /** 62.5 -> "62,5%" */
    fun percentual(valor: Double): String = String.format(BR, "%.1f%%", valor)

    /** 0.42 -> "▲ 0,4" · -0.3 -> "▼ 0,3" · 0 -> "estável" */
    fun variacao(valor: Double): String = when {
        valor > 0.05  -> "▲ ${String.format(BR, "%.1f", valor)}"
        valor < -0.05 -> "▼ ${String.format(BR, "%.1f", -valor)}"
        else          -> "estável"
    }
}
