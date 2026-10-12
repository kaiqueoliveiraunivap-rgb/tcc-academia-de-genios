package com.github.carloseduardofsousa.tcc

import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

object NavegacaoUi {
    fun adicionarVoltar(activity: AppCompatActivity) {
        val raiz = activity.findViewById<ViewGroup>(android.R.id.content).getChildAt(0)
        val coluna = when (raiz) {
            is LinearLayout -> raiz
            is ScrollView -> raiz.getChildAt(0) as? LinearLayout
            else -> null
        } ?: return
        val escala = activity.resources.displayMetrics.density
        fun dp(valor: Int) = (valor * escala).toInt()
        coluna.addView(TextView(activity).apply {
            text = "‹  Voltar"
            contentDescription = "Voltar à tela anterior"
            textSize = 16f
            setTextColor(activity.getColor(R.color.Destaque))
            gravity = android.view.Gravity.CENTER_VERTICAL
            minHeight = dp(48)
            setPadding(if (coluna.paddingLeft == 0) dp(24) else 0, 0, 0, 0)
            setOnClickListener { activity.finish() }
        }, 0, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(48)))
    }
}
