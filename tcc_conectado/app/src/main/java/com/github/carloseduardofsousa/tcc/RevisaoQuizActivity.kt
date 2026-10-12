package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class RevisaoQuizActivity : AppCompatActivity() {
    private lateinit var lista: LinearLayout
    private lateinit var status: TextView
    private lateinit var progresso: ProgressBar
    private var quiz: JSONObject? = null
    private var ocupado = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this) || Sessao.tipoPerfil(this) != "aluno") { finish(); return }
        val root = EstudoUi.raiz(this)
        root.addView(EstudoUi.botao(this, "← Voltar") { finish() })
        root.addView(EstudoUi.texto(this, "Revisão do quiz", 26f, true))
        status = EstudoUi.texto(this, "Carregando suas respostas…"); root.addView(status)
        progresso = ProgressBar(this); root.addView(progresso)
        lista = EstudoUi.coluna(this)
        root.addView(ScrollView(this).apply { addView(lista) }, LinearLayout.LayoutParams(-1, 0, 1f))
        setContentView(root)
        quiz = savedInstanceState?.getString("quiz")?.let { JSONObject(it) }
        quiz?.let { mostrar(it) } ?: carregar()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState); outState.putString("quiz", quiz?.toString())
    }

    private fun carregar() {
        if (ocupado) return
        ocupado = true; lista.removeAllViews(); progresso.visibility = View.VISIBLE
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) { ApiClient.postComStatus("revisar_estudo.php", mapOf(
                "token" to Sessao.token(this@RevisaoQuizActivity), "id_quiz" to intent.getIntExtra("ID_QUIZ", 0).toString()
            )) }
            ocupado = false; progresso.visibility = View.GONE
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@RevisaoQuizActivity); return@launch }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                status.text = resposta.mensagemErro("Não foi possível carregar a revisão.")
                lista.addView(EstudoUi.botao(this@RevisaoQuizActivity, "Tentar novamente") { carregar() }); return@launch
            }
            quiz = json.getJSONObject("quiz"); mostrar(quiz!!)
        }
    }

    private fun mostrar(dados: JSONObject) {
        progresso.visibility = View.GONE; lista.removeAllViews()
        status.text = "${dados.getString("materia")} · ${dados.getInt("acertos")} de ${dados.getInt("total")} acertos\n${EstudoUi.data(dados.getString("criado_em"))}"
        lista.addView(EstudoUi.texto(this, dados.getString("capitulo"), 20f, true))
        val questoes = dados.getJSONArray("questoes")
        for (i in 0 until questoes.length()) {
            val q = questoes.getJSONObject(i)
            val escolhida = q.optInt("escolhida", -1)
            val card = EstudoUi.cartao(this)
            card.addView(EstudoUi.texto(this, "Questão ${i + 1} · " + when {
                q.isNull("acertou") -> "Resultado não registrado"
                q.getBoolean("acertou") -> "Você acertou"
                else -> "Você errou"
            }, 16f, true))
            card.addView(EstudoUi.texto(this, q.getString("enunciado"), 18f))
            val alternativas = q.getJSONArray("alternativas")
            for (j in 0 until alternativas.length()) {
                val alt = alternativas.getJSONObject(j)
                val correta = alt.getBoolean("ehCorreta")
                val marcacao = buildList {
                    if (j == escolhida) add("Sua resposta")
                    if (correta) add("Correta")
                }.joinToString(" · ")
                card.addView(EstudoUi.texto(this, "${'A' + j}) ${alt.getString("texto")}" + if (marcacao.isEmpty()) "" else "\n$marcacao", 16f, correta || j == escolhida))
            }
            if (escolhida < 0) card.addView(EstudoUi.texto(this, "Tentativa antiga: a alternativa escolhida não foi registrada."))
            card.addView(EstudoUi.texto(this, "Por que essa é a resposta?", 17f, true))
            card.addView(EstudoUi.texto(this, q.getString("explicacao")).also { EstudoUi.justificar(it) })
            lista.addView(card)
        }
        if (dados.optBoolean("pode_refazer")) lista.addView(EstudoUi.botao(this, "Estudar este capítulo e refazer") {
            startActivity(Intent(this, CapitulosActivity::class.java).apply {
                putExtra("ID_FRENTE", dados.getInt("id_frente")); putExtra("FRENTE", dados.optString("frente"))
                putExtra("MATERIA", dados.getString("materia")); putExtra("ID_CAPITULO_ABRIR", dados.getInt("id_capitulo"))
                putExtra("CAPITULO_ABRIR", dados.getString("capitulo"))
                putExtra("ID_USUARIO", Sessao.idUsuario(this@RevisaoQuizActivity))
                putExtra("NOME_USUARIO", Sessao.nome(this@RevisaoQuizActivity)); putExtra("ID_TURMA", Sessao.idTurma(this@RevisaoQuizActivity))
            })
        }) else lista.addView(EstudoUi.texto(this, "Este capítulo não está mais disponível para novas tentativas."))
    }
}
