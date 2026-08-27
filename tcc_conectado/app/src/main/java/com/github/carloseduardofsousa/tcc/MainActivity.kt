package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import org.json.JSONArray

class   MainActivity : AppCompatActivity() {

    data class Alternativa(val id: Int, val texto: String, val ehCorreta: Boolean)
    data class Pergunta(val id: Int, val enunciado: String, val alternativas: List<Alternativa>)

    private var idUsuario   = 0
    private var nomeUsuario = ""
    private var idTurma     = 1

    private var indiceAtual              = 0
    private var alternativaSelecionadaId = -1
    private var acertos                  = 0
    private var aguardandoProxima        = false

    private lateinit var txtPergunta:        TextView
    private lateinit var txtContador:        TextView
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

        idUsuario   = intent.getIntExtra("ID_USUARIO", 0)
        nomeUsuario = intent.getStringExtra("NOME_USUARIO") ?: "Aluno"
        idTurma     = intent.getIntExtra("ID_TURMA", 1)

        inicializarComponentes()
        carregarQuestoes()
    }

    private fun inicializarComponentes() {
        txtPergunta       = findViewById(R.id.txtPergunta)
        txtContador       = findViewById(R.id.tvContador)
        btnOptA           = findViewById(R.id.btnOpcaoA)
        btnOptB           = findViewById(R.id.btnOpcaoB)
        btnOptC           = findViewById(R.id.btnOpcaoC)
        btnOptD           = findViewById(R.id.btnOpcaoD)
        btnConfirmar      = findViewById(R.id.btnConfirmar)
        barraProgresso    = findViewById(R.id.progressBar)
        progressCarregando = findViewById(R.id.progressCarregando)
    }

    private fun carregarQuestoes(idMateria: Int = 1) {
        progressCarregando.visibility = View.VISIBLE
        setQuizEnabled(false)

        // Simulando um tempo de carregamento de 1 segundo para testar o visual
        Thread {
            Thread.sleep(1000)

            // CRIANDO DADOS FAKES (Substitui a chamada da API)
            val perguntasTemp = mutableListOf<Pergunta>()

            // Pergunta 1
            perguntasTemp.add(Pergunta(
                id = 101,
                enunciado = "Quanto é 2 + 2?",
                alternativas = listOf(
                    Alternativa(1, "3", false),
                    Alternativa(2, "4", true),
                    Alternativa(3, "5", false),
                    Alternativa(4, "6", false)
                )
            ))

            // Pergunta 2
            perguntasTemp.add(Pergunta(
                id = 102,
                enunciado = "Qual a capital do Brasil?",
                alternativas = listOf(
                    Alternativa(5, "Rio de Janeiro", false),
                    Alternativa(6, "São Paulo", false),
                    Alternativa(7, "Brasília", true),
                    Alternativa(8, "Salvador", false)
                )
            ))

            runOnUiThread {
                progressCarregando.visibility = View.GONE

                listaDePerguntas.clear()
                listaDePerguntas.addAll(perguntasTemp)

                setQuizEnabled(true)
                atualizarTela()
                configurarCliques()
            }
        }.start()
    }

    private fun setQuizEnabled(enabled: Boolean) {
        listOf(btnOptA, btnOptB, btnOptC, btnOptD, btnConfirmar).forEach { it.isEnabled = enabled }
    }

    private fun atualizarTela() {
        val perguntaAtual = listaDePerguntas[indiceAtual]
        val total = listaDePerguntas.size

        txtPergunta.text  = perguntaAtual.enunciado
        txtContador.text  = "QUESTAO ${indiceAtual + 1}/$total"
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
            }
        }

        btnConfirmar.setOnClickListener {
            if (aguardandoProxima) avancarPergunta()
            else if (alternativaSelecionadaId != -1) validarResposta()
        }
    }

    private fun validarResposta() {
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
            acertos++
            btnConfirmar.text = "CORRETO! PROXIMA"
        } else {
            botoes.getOrNull(idxErrado)?.let {
                it.backgroundTintList = ContextCompat.getColorStateList(this, R.color.OptErrada)
                it.setTextColor(Color.WHITE)
            }
            btnConfirmar.text = "ERROU! PROXIMA"
        }

        // As questões vêm da Gemini e não existem na tabela questao, então o
        // servidor grava o enunciado. Antes o app mandava id_questao/id_alternativa,
        // que o endpoint não lê: nenhuma resposta chegava a ser registrada.
        val enunciado = pergunta.enunciado
        val acertou   = if (altSelecionada.ehCorreta) "1" else "0"
        Thread {
            ApiClient.post("registrar_resposta.php", mapOf(
                "token"     to Sessao.token(this),
                "enunciado" to enunciado,
                "acertou"   to acertou
            ))
        }.start()
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
        val total  = listaDePerguntas.size
        val mesRef = java.text.SimpleDateFormat("yyyy-MM", java.util.Locale.getDefault()).format(java.util.Date())

        Thread {
            // Quem pontua é o dono do token; o servidor ignora qualquer id_aluno
            // enviado daqui, então mandá-lo seria informação que ele descarta.
            ApiClient.post("atualizar_ranking.php", mapOf(
                "token"          to Sessao.token(this),
                "id_turma"       to idTurma.toString(),
                "pontos"         to acertos.toString(),
                "mes_referencia" to mesRef
            ))
        }.start()

        val intent = Intent(this, ResultadoActivity::class.java)
        intent.putExtra("ACERTOS",      acertos)
        intent.putExtra("TOTAL",        total)
        intent.putExtra("ID_USUARIO",   idUsuario)
        intent.putExtra("NOME_USUARIO", nomeUsuario)
        intent.putExtra("ID_TURMA",     idTurma)
        intent.putExtra("MES_REF",      mesRef)
        startActivity(intent)
        finish()
    }
}
