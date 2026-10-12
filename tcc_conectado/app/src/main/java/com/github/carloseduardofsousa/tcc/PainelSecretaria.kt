package com.github.carloseduardofsousa.tcc

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast

object PainelSecretaria {
    fun abrir(context: Context) {
        val url = ApiConfig.BASE_URL.trimEnd('/') + "/coordenacao/"
        try {
            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
        } catch (_: ActivityNotFoundException) {
            Toast.makeText(context, "Instale um navegador para abrir o painel da secretaria.", Toast.LENGTH_LONG).show()
        }
    }
}
