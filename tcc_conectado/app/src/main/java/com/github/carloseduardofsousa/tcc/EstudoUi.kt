package com.github.carloseduardofsousa.tcc

import android.content.Context
import android.content.res.ColorStateList
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.text.Layout
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Locale

/** Componentes pequenos compartilhados pelas telas de histórico e revisão. */
object EstudoUi {
    fun dp(context: Context, n: Int) = (context.resources.displayMetrics.density * n).toInt()
    fun nomeLivro(nome: String) = nome
        .replace(Regex("\\s*[-–]?\\s*@POLIEDRO\\d{4}\\.pdf$", RegexOption.IGNORE_CASE), "")
        .replace(Regex("\\.pdf$", RegexOption.IGNORE_CASE), "")
        .trim()
    fun texto(context: Context, valor: String, tamanho: Float = 16f, destaque: Boolean = false) = TextView(context).apply {
        text = valor; textSize = tamanho
        setTextColor(UiPaleta.hex(if (destaque) "#1A237E" else "#37474F"))
        if (destaque) typeface = Typeface.DEFAULT_BOLD
        setLineSpacing(dp(context, 3).toFloat(), 1f)
        setPadding(0, dp(context, 5), 0, dp(context, 7))
    }
    fun botao(context: Context, titulo: String, acao: () -> Unit) = Button(context).apply {
        text = titulo; isAllCaps = false; textSize = 16f; minHeight = dp(context, 48)
        setTextColor(UiPaleta.hex("#1A237E"))
        backgroundTintList = ColorStateList.valueOf(UiPaleta.hex("#E8EAF6"))
        setOnClickListener { acao() }
    }
    fun coluna(context: Context) = LinearLayout(context).apply { orientation = LinearLayout.VERTICAL }
    fun raiz(context: Context) = coluna(context).apply {
        setBackgroundColor(UiPaleta.hex("#F5F7FA"))
        val p = dp(context, 20); setPadding(p, p, p, p)
        protegerBarras(this)
    }
    fun protegerBarras(alvo: View, incluirTeclado: Boolean = false) {
        val esquerda = alvo.paddingLeft; val topo = alvo.paddingTop
        val direita = alvo.paddingRight; val base = alvo.paddingBottom
        ViewCompat.setOnApplyWindowInsetsListener(alvo) { view, insets ->
            val tipos = WindowInsetsCompat.Type.systemBars() or
                if (incluirTeclado) WindowInsetsCompat.Type.ime() else 0
            val bars = insets.getInsets(tipos)
            view.setPadding(esquerda + bars.left, topo + bars.top, direita + bars.right, base + bars.bottom)
            insets
        }
        ViewCompat.requestApplyInsets(alvo)
    }
    fun cartao(context: Context) = coluna(context).apply {
        val p = dp(context, 16); setPadding(p, p, p, p)
        background = GradientDrawable().apply {
            setColor(UiPaleta.superficie()); cornerRadius = dp(context, 16).toFloat()
            setStroke(dp(context, 1), UiPaleta.hex("#E1E3EE"))
        }
        layoutParams = LinearLayout.LayoutParams(-1, -2).apply { bottomMargin = dp(context, 12) }
    }
    fun justificar(texto: TextView) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) texto.justificationMode = Layout.JUSTIFICATION_MODE_INTER_WORD
        texto.setTextIsSelectable(true)
    }
    fun data(valor: String): String = runCatching {
        val locale = Locale.forLanguageTag("pt-BR")
        val data = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", locale).parse(valor)!!
        SimpleDateFormat("dd/MM/yyyy 'às' HH:mm", locale).format(data)
    }.getOrDefault(valor)
    fun percentual(acertos: Int, total: Int) = if (total > 0) (100 * acertos / total) else 0
}

/** Invalida somente as telas de progresso abertas depois de salvar uma tentativa. */
object ProgressoAluno { var versao: Int = 0; private set
    fun atualizar() { versao++ }
}

fun ApiResposta.mensagemErro(padrao: String): String {
    if (codigo == 0) return "Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente."
    return runCatching { JSONObject(corpo.orEmpty()).optString("mensagem").takeIf { it.isNotBlank() } }.getOrNull() ?: padrao
}
