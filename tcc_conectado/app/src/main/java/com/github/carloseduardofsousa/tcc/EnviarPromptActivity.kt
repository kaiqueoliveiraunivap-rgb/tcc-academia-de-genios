package com.github.carloseduardofsousa.tcc

import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.core.widget.addTextChangedListener
import com.google.android.material.textfield.TextInputEditText
import org.json.JSONObject

class EnviarPromptActivity : AppCompatActivity() {

    private data class MateriaChip(val nome: String, val emoji: String)

    private lateinit var containerMaterias: LinearLayout
    private lateinit var btnFacil: Button
    private lateinit var btnMedio: Button
    private lateinit var btnDificil: Button
    private lateinit var etInstrucao: TextInputEditText
    private lateinit var btnGerarPreview: Button
    private lateinit var cardPreview: CardView
    private lateinit var txtPreviewPrompt: TextView
    private lateinit var progressBar: ProgressBar
    private lateinit var btnEnviar: Button

    private var nomeProfessor = ""
    private var idTurma = 0
    private var idAluno = 0
    private var nomeAluno = ""
    private var pontosAluno = 0

    private var materiaSelecionada: String? = null
    private var dificuldade = "MEDIO"
    private var promptFinalAtual: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_enviar_prompt)

        nomeProfessor = intent.getStringExtra("NOME_PROFESSOR") ?: "Professor(a)"
        idTurma       = intent.getIntExtra("ID_TURMA", 0)
        idAluno       = intent.getIntExtra("ID_ALUNO", 0)
        nomeAluno     = intent.getStringExtra("NOME_ALUNO") ?: "Aluno"
        pontosAluno   = intent.getIntExtra("PONTOS_ALUNO", 0)

        findViewById<TextView>(R.id.txtTituloPrompt).text = "Prompt para $nomeAluno"
        findViewById<TextView>(R.id.txtSubtituloPrompt).text =
            "$pontosAluno pontos acumulados nesta turma"

        containerMaterias = findViewById(R.id.containerMaterias)
        btnFacil          = findViewById(R.id.btnFacilPrompt)
        btnMedio          = findViewById(R.id.btnMedioPrompt)
        btnDificil        = findViewById(R.id.btnDificilPrompt)
        etInstrucao       = findViewById(R.id.etInstrucaoProfessor)
        btnGerarPreview   = findViewById(R.id.btnGerarPreview)
        cardPreview       = findViewById(R.id.cardPreview)
        txtPreviewPrompt  = findViewById(R.id.txtPreviewPrompt)
        progressBar       = findViewById(R.id.progressBarEnviarPrompt)
        btnEnviar         = findViewById(R.id.btnEnviarPrompt)

        montarChipsMaterias()

        btnFacil.setOnClickListener   { selecionarDificuldade("FACIL") }
        btnMedio.setOnClickListener   { selecionarDificuldade("MEDIO") }
        btnDificil.setOnClickListener { selecionarDificuldade("DIFICIL") }
        selecionarDificuldade("MEDIO")

        btnGerarPreview.setOnClickListener { gerarPreview() }
        btnEnviar.setOnClickListener { enviarPrompt() }

        // Qualquer alteração depois de gerar a prévia invalida o prompt já montado,
        // evitando enviar um texto que não reflete mais o que está na tela.
        etInstrucao.addTextChangedListener { invalidarPreview() }
    }

    private fun montarChipsMaterias() {
        val materias = listOf(
            MateriaChip("Matemática", "📐"),
            MateriaChip("História",   "📜"),
            MateriaChip("Geografia",  "🌍"),
            MateriaChip("Ciências",   "🔬"),
            MateriaChip("Português",  "📖"),
            MateriaChip("Inglês",     "🇺🇸"),
            MateriaChip("Física",     "⚛️"),
            MateriaChip("Química",    "🧪"),
            MateriaChip("Biologia",   "🧬"),
            MateriaChip("Filosofia",  "🤔"),
        )

        val chips = materias.map { materia ->
            TextView(this).apply {
                text = "${materia.emoji}  ${materia.nome}"
                textSize = 13f
                setPadding(28, 18, 28, 18)
                setTextColor(Color.parseColor("#333333"))
                background = GradientDrawable().apply {
                    cornerRadius = 40f
                    setColor(Color.parseColor("#F0F0F5"))
                }
                layoutParams = ViewGroup.MarginLayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT
                ).apply { setMargins(0, 0, 12, 0) }
                gravity = Gravity.CENTER
            }
        }

        chips.forEachIndexed { i, chip ->
            chip.setOnClickListener {
                materiaSelecionada = materias[i].nome
                chips.forEach {
                    it.setTextColor(Color.parseColor("#333333"))
                    (it.background as GradientDrawable).setColor(Color.parseColor("#F0F0F5"))
                }
                chip.setTextColor(Color.WHITE)
                (chip.background as GradientDrawable).setColor(Color.parseColor("#5C6BC0"))
                invalidarPreview()
            }
            containerMaterias.addView(chip)
        }
    }

    private fun selecionarDificuldade(nivel: String) {
        dificuldade = nivel
        listOf(btnFacil to "FACIL", btnMedio to "MEDIO", btnDificil to "DIFICIL").forEach { (btn, n) ->
            val sel = n == nivel
            btn.backgroundTintList = android.content.res.ColorStateList.valueOf(
                if (sel) Color.parseColor("#5C6BC0") else Color.parseColor("#EEEEEE")
            )
            btn.setTextColor(if (sel) Color.WHITE else Color.parseColor("#555555"))
        }
        invalidarPreview()
    }

    private fun invalidarPreview() {
        if (promptFinalAtual != null) {
            promptFinalAtual = null
            cardPreview.visibility = View.GONE
            btnEnviar.isEnabled = false
            btnEnviar.alpha = 0.5f
        }
    }

    private fun gerarPreview() {
        val materia = materiaSelecionada
        val instrucao = etInstrucao.text?.toString()?.trim().orEmpty()

        if (materia == null) {
            Toast.makeText(this, "Escolha a matéria.", Toast.LENGTH_SHORT).show()
            return
        }
        if (instrucao.isEmpty()) {
            Toast.makeText(this, "Escreva o que a IA deve priorizar para o aluno.", Toast.LENGTH_SHORT).show()
            return
        }

        val prompt = PromptProfessorBuilder.montar(
            nomeAluno       = nomeAluno,
            nomeProfessor   = nomeProfessor,
            materia         = materia,
            dificuldade     = dificuldade,
            instrucaoBruta  = instrucao,
            pontosTotal     = pontosAluno
        )

        promptFinalAtual = prompt
        txtPreviewPrompt.text = prompt
        cardPreview.visibility = View.VISIBLE
        btnEnviar.isEnabled = true
        btnEnviar.alpha = 1f
    }

    private fun enviarPrompt() {
        val materia = materiaSelecionada ?: return
        val instrucao = etInstrucao.text?.toString()?.trim().orEmpty()
        val promptFinal = promptFinalAtual ?: return

        progressBar.visibility = View.VISIBLE
        btnEnviar.isEnabled = false

        Thread {
            // O id_professor não vai mais na requisição: o servidor tira o autor
            // do token, então mandá-lo seria informação que ele ignora.
            val resposta = ApiClient.postComStatus(
                "enviar_prompt_professor.php",
                mapOf(
                    "token"        to Sessao.token(this),
                    "id_aluno"     to idAluno.toString(),
                    "id_turma"     to idTurma.toString(),
                    "materia"      to materia,
                    "dificuldade"  to dificuldade,
                    "instrucao"    to instrucao,
                    "prompt_final" to promptFinal
                )
            )

            runOnUiThread {
                progressBar.visibility = View.GONE
                btnEnviar.isEnabled = true

                if (resposta.sessaoExpirada) {
                    Toast.makeText(this, "Sessão expirada. Faça login novamente.", Toast.LENGTH_LONG).show()
                    Sessao.encerrar(this)
                    finish()
                    return@runOnUiThread
                }

                if (resposta.corpo.isNullOrBlank()) {
                    Toast.makeText(this, "Erro de conexão. Verifique o servidor.", Toast.LENGTH_LONG).show()
                    return@runOnUiThread
                }

                try {
                    val json = JSONObject(resposta.corpo)
                    if (json.optString("status") == "sucesso") {
                        Toast.makeText(
                            this,
                            "Prompt enviado! $nomeAluno vai receber essa orientação no próximo quiz.",
                            Toast.LENGTH_LONG
                        ).show()
                        finish()
                    } else {
                        Toast.makeText(this, json.optString("mensagem", "Não foi possível enviar."), Toast.LENGTH_LONG).show()
                    }
                } catch (e: Exception) {
                    Toast.makeText(this, "Resposta inesperada do servidor.", Toast.LENGTH_SHORT).show()
                }
            }
        }.start()
    }
}
