package com.github.carloseduardofsousa.tcc

import java.util.Locale

/** Siglas curtas para identificar matérias sem depender da aparência dos emojis do aparelho. */
object MateriaVisual {
    fun sigla(nome: String): String = when (nome.lowercase(Locale.ROOT)) {
        "matemática", "matematica" -> "MT"
        "história", "historia" -> "HI"
        "geografia" -> "GE"
        "ciências", "ciencias" -> "CI"
        "português", "portugues" -> "PT"
        "inglês", "ingles" -> "IN"
        "física", "fisica" -> "FI"
        "química", "quimica" -> "QU"
        "biologia" -> "BI"
        "filosofia" -> "FL"
        "sociologia" -> "SO"
        "redação", "redacao" -> "RE"
        else -> nome.take(2).uppercase(Locale.ROOT)
    }
}
