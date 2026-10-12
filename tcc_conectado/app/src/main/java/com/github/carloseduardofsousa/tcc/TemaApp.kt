package com.github.carloseduardofsousa.tcc

import android.app.Application
import android.content.Context
import android.graphics.Color
import androidx.appcompat.app.AppCompatDelegate
import java.util.Locale

class AcademiaApp : Application() {
    override fun onCreate() {
        super.onCreate()
        TemaApp.iniciar(this)
    }
}

object TemaApp {
    private const val ARQUIVO = "aparencia_academia"
    private const val CHAVE = "tema"
    private var escuroAtivo = false

    fun iniciar(context: Context) {
        escuroAtivo = context.getSharedPreferences(ARQUIVO, Context.MODE_PRIVATE)
            .getString(CHAVE, "claro") == "escuro"
        AppCompatDelegate.setDefaultNightMode(
            if (escuroAtivo) AppCompatDelegate.MODE_NIGHT_YES else AppCompatDelegate.MODE_NIGHT_NO
        )
    }

    fun selecionar(context: Context, escuro: Boolean) {
        escuroAtivo = escuro
        context.getSharedPreferences(ARQUIVO, Context.MODE_PRIVATE).edit()
            .putString(CHAVE, if (escuro) "escuro" else "claro").apply()
        AppCompatDelegate.setDefaultNightMode(
            if (escuro) AppCompatDelegate.MODE_NIGHT_YES else AppCompatDelegate.MODE_NIGHT_NO
        )
    }

    fun escuro() = escuroAtivo
}

/** Adapta as cores das telas que ainda constroem componentes em Kotlin. */
object UiPaleta {
    fun hex(valor: String): Int {
        if (!TemaApp.escuro()) return Color.parseColor(valor)
        val adaptada = when (valor.uppercase(Locale.ROOT)) {
            "#FFFFFF" -> "#232D43"
            "#FAFAFA", "#F8F8FC", "#F5F7FA" -> "#151B2A"
            "#1A237E", "#1A1A2E", "#333333", "#24243B", "#37474F" -> "#F0F3FB"
            "#303F9F" -> "#C7D1FF"
            "#455A64", "#555555", "#666680", "#737386" -> "#B8C3D5"
            "#999999", "#9999AA" -> "#A7B2C8"
            "#E8EAF6", "#EEF0FF", "#F0F0F5", "#EEEEEE" -> "#303B55"
            "#E1E3EE", "#E4E6F2", "#E7E9F2", "#EEEEF2" -> "#3D4961"
            "#9FA8DA" -> "#B5C0F5"
            else -> valor
        }
        return Color.parseColor(adaptada)
    }

    fun superficie() = hex("#FFFFFF")
}
