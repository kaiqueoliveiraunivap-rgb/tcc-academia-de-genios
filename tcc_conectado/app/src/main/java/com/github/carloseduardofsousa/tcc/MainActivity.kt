package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.os.Build
import android.text.Layout
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import android.widget.ScrollView
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

class   MainActivity : AppCompatActivity() {

    data class Alternativa(val id: Int, val texto: String, val ehCorreta: Boolean)
    data class Pergunta(
        val id: Int, val enunciado: String, val alternativas: List<Alternativa>,
        val explicacao: String
    )

    private var idUsuario   = 0
    private var nomeUsuario = ""
    private var idTurma     = 1

    private var indiceAtual              = 0
    private var alternativaSelecionadaId = -1
    private var acertos                  = 0
    private var aguardandoProxima        = false
    private var salvandoResultado         = false
    private var tentativa                 = UUID.randomUUID().toString()
    private val respostas                 = mutableListOf<Int>()

    private lateinit var txtPergunta:        TextView
    private lateinit var txtContador:        TextView
    private lateinit var txtExplicacao:      TextView
    private lateinit var btnOptA:            Button
    private lateinit var btnOptB:            Button
    private lateinit var btnOptC:            Button
    private lateinit var btnOptD:            Button
    private lateinit var btnConfirmar:       Button
    private lateinit var barraProgresso:     ProgressBar
    private lateinit var progressCarregando: ProgressBar

    private val listaDePerguntas = mutableListOf<Pergunta>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        NavegacaoUi.adicionarVoltar(this)
        EstudoUi.protegerBarras(findViewById(R.id.main))

        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); finish(); return }
        idUsuario   = intent.getIntExtra("ID_USUARIO", Sessao.idUsuario(this))
        nomeUsuario = intent.getStringExtra("NOME_USUARIO") ?: Sessao.nome(this)
        idTurma     = intent.getIntExtra("ID_TURMA", Sessao.idTurma(this))

        inicializarComponentes()
        carregarQuestoes()
        if (savedInstanceState != null && listaDePerguntas.isNotEmpty()) {
            indiceAtual = savedInstanceState.getInt("indice").coerceIn(0, listaDePerguntas.lastIndex)
            acertos = savedInstanceState.getInt("acertos")
            tentativa = savedInstanceState.getString("tentativa") ?: tentativa
            runCatching { JSONArray(savedInstanceState.getString("respostas")) }.getOrNull()?.let { array ->
                for (i in 0 until minOf(array.length(), respostas.size)) respostas[i] = array.optInt(i, -1)
            }
            atualizarTela()
            val selecionada = savedInstanceState.getInt("selecionada", -1)
            val idx = listaDePerguntas[indiceAtual].alternativas.indexOfFirst { it.id == selecionada }
            if (idx >= 0) listOf(btnOptA,btnOptB,btnOptC,btnOptD)[idx].performClick()
            if (savedInstanceState.getBoolean("respondida") && idx >= 0) validarResposta(restaurando = true)
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putInt("indice", indiceAtual)
        outState.putInt("acertos", acertos)
        outState.putInt("selecionada", alternativaSelecionadaId)
        outState.putBoolean("respondida", aguardandoProxima)
        outState.putString("tentativa", tentativa)
        outState.putString("respostas", JSONArray().apply { respostas.forEach { put(it) } }.toString())
    }

    private fun inicializarComponentes() {
        txtPergunta       = findViewById(R.id.txtPergunta)
        txtContador       = findViewById(R.id.tvContador)
        txtExplicacao      = findViewById(R.id.txtExplicacao)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            txtExplicacao.justificationMode = Layout.JUSTIFICATION_MODE_INTER_WORD
        }
        btnOptA           = findViewById(R.id.btnOpcaoA)
        btnOptB           = findViewById(R.id.btnOpcaoB)
        btnOptC           = findViewById(R.id.btnOpcaoC)
        btnOptD           = findViewById(R.id.btnOpcaoD)
        btnConfirmar      = findViewById(R.id.btnConfirmar)
        barraProgresso    = findViewById(R.id.progressBar)
        progressCarregando = findViewById(R.id.progressCarregando)
    }

    private fun carregarQuestoes() {
        progressCarregando.visibility = View.GONE
        val raw = intent.getStringExtra("QUESTOES_JSON")
        if (raw == null) {
            startActivity(Intent(this, SelecionarMateriaActivity::class.java).apply {
                this@MainActivity.intent.extras?.let { putExtras(it) }
            })
            finish()
            return
        }
        try {
            val arr = JSONArray(raw)
            require(arr.length() == 5)
            for (i in 0 until arr.length()) {
                val q = arr.getJSONObject(i)
                val alts = q.getJSONArray("alternativas")
                require(alts.length() == 4)
                val alternativas = (0 until 4).map { j ->
                    val a = alts.getJSONObject(j)
                    Alternativa(i * 4 + j + 1, a.getString("texto"), a.getBoolean("ehCorreta"))
                }
                require(alternativas.count { it.ehCorreta } == 1)
                listaDePerguntas.add(Pergunta(
                    i + 1, q.getString("enunciado"), alternativas,
                    q.getString("explicacao")
                ))
            }
            configurarCliques()
            respostas.clear()
            repeat(arr.length()) { respostas.add(-1) }
            atualizarTela()
        } catch (e: Exception) {
            Toast.makeText(this, "Quiz inválido. Gere o estudo novamente.", Toast.LENGTH_LONG).show()
            finish()
        }
    }

    private fun setQuizEnabled(enabled: Boolean) {
        listOf(btnOptA, btnOptB, btnOptC, btnOptD, btnConfirmar).forEach { it.isEnabled = enabled }
    }

    private fun atualizarTela() {
        val perguntaAtual = listaDePerguntas[indiceAtual]
        val total = listaDePerguntas.size

        txtPergunta.text  = perguntaAtual.enunciado
        txtContador.text  = "QUESTÃO ${indiceAtual + 1}/$total"
        barraProgresso.progress = ((indiceAtual.toFloat() / total) * 100).toInt()

        val alts   = perguntaAtual.alternativas
        val botoes = listOf(btnOptA, btnOptB, btnOptC, btnOptD)

        botoes.forEachIndexed { idx, btn ->
            btn.text       = alts.getOrNull(idx)?.texto ?: ""
            btn.visibility = if (idx < alts.size) View.VISIBLE else View.INVISIBLE
        }

        alternativaSelecionadaId = -1
        aguardandoProxima        = false
        btnConfirmar.text        = "CONFIRMAR"
        btnConfirmar.isEnabled   = false
        txtExplicacao.visibility = View.GONE
        findViewById<ScrollView>(R.id.scrollQuestao).post { findViewById<ScrollView>(R.id.scrollQuestao).scrollTo(0, 0) }

        botoes.forEach {
            it.isEnabled        = true
            it.backgroundTintList = ContextCompat.getColorStateList(this, R.color.btnOpcoes)
            it.setTextColor(ContextCompat.getColor(this, R.color.BordaButton))
        }
    }

    private fun configurarCliques() {
        val botoes = listOf(btnOptA, btnOptB, btnOptC, btnOptD)

        botoes.forEach { botao ->
            botao.setOnClickListener {
                if (aguardandoProxima) return@setOnClickListener
                val idx = botoes.indexOf(botao)
                alternativaSelecionadaId = listaDePerguntas[indiceAtual].alternativas.getOrNull(idx)?.id ?: -1
                botoes.forEach {
                    it.backgroundTintList = ContextCompat.getColorStateList(this, R.color.btnOpcoes)
                    it.setTextColor(ContextCompat.getColor(this, R.color.BordaButton))
                }
                botao.backgroundTintList = ContextCompat.getColorStateList(this, R.color.btnSelecionado)
                botao.setTextColor(Color.WHITE)
                btnConfirmar.isEnabled = true
            }
        }

        btnConfirmar.setOnClickListener {
            if (aguardandoProxima) avancarPergunta()
            else if (alternativaSelecionadaId != -1) validarResposta()
        }
    }

    private fun validarResposta(restaurando: Boolean = false) {
        val pergunta      = listaDePerguntas[indiceAtual]
        val altSelecionada = pergunta.alternativas.find { it.id == alternativaSelecionadaId } ?: return
        val botoes        = listOf(btnOptA, btnOptB, btnOptC, btnOptD)

        botoes.forEach { it.isEnabled = false }
        aguardandoProxima = true

        val idxCorreto = pergunta.alternativas.indexOfFirst { it.ehCorreta }
        val idxErrado  = pergunta.alternativas.indexOf(altSelecionada)

        botoes.getOrNull(idxCorreto)?.let {
            it.backgroundTintList = ContextCompat.getColorStateList(this, R.color.OptCerta)
            it.setTextColor(Color.WHITE)
        }

        if (altSelecionada.ehCorreta) {
            if (!restaurando) acertos++
            btnConfirmar.text = if (indiceAtual == listaDePerguntas.lastIndex) "CORRETO! VER RESULTADO" else "CORRETO! PRÓXIMA"
        } else {
            botoes.getOrNull(idxErrado)?.let {
                it.backgroundTintList = ContextCompat.getColorStateList(this, R.color.OptErrada)
                it.setTextColor(Color.WHITE)
            }
            btnConfirmar.text = if (indiceAtual == listaDePerguntas.lastIndex) "ERROU! VER RESULTADO" else "ERROU! PRÓXIMA"
        }

        txtExplicacao.text = pergunta.explicacao
        txtExplicacao.visibility = View.VISIBLE
        if (restaurando) return
        respostas[indiceAtual] = idxErrado
    }

    private fun avancarPergunta() {
        if (indiceAtual < listaDePerguntas.size - 1) {
            indiceAtual++
            atualizarTela()
        } else {
            finalizarQuiz()
        }
    }

    private fun finalizarQuiz() {
        if (salvandoResultado) return
        val idEstudo = intent.getIntExtra("ID_ESTUDO", 0)
        if (idEstudo <= 0 || respostas.size != 5 || respostas.any { it !in 0..3 }) {
            Toast.makeText(this, "Quiz incompleto. Volte ao capítulo e tente novamente.", Toast.LENGTH_LONG).show()
            return
        }
        salvandoResultado = true
        progressCarregando.visibility = View.VISIBLE
        btnConfirmar.isEnabled = false
        btnConfirmar.text = "Salvando resultado…"
        val params = mutableMapOf(
            "token" to Sessao.token(this),
            "id_estudo" to idEstudo.toString(),
            "tentativa" to tentativa,
            "respostas" to JSONArray().apply { respostas.forEach { put(it) } }.toString()
        )
        if (idTurma > 0) params["id_turma"] = idTurma.toString()
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("concluir_estudo.php", params, 20000)
            }
            progressCarregando.visibility = View.GONE
            salvandoResultado = false
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@MainActivity); finish(); return@launch }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                btnConfirmar.isEnabled = true
                btnConfirmar.text = "Tentar salvar novamente"
                Toast.makeText(this@MainActivity, resposta.mensagemErro("Não foi possível salvar. Suas respostas estão mantidas nesta tela."), Toast.LENGTH_LONG).show()
                return@launch
            }
            val mesRef = java.text.SimpleDateFormat("yyyy-MM", java.util.Locale.getDefault()).format(java.util.Date())
            ProgressoAluno.atualizar()
            startActivity(Intent(this@MainActivity, ResultadoActivity::class.java).apply {
                putExtra("ID_QUIZ", json.getInt("id_quiz"))
                putExtra("ACERTOS", json.getInt("acertos"))
                putExtra("TOTAL", json.getInt("total"))
                putExtra("ID_USUARIO", idUsuario)
                putExtra("NOME_USUARIO", nomeUsuario)
                putExtra("ID_TURMA", idTurma)
                putExtra("MES_REF", mesRef)
            })
            finish()
        }
    }
}
