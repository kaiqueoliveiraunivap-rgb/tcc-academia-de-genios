package com.github.carloseduardofsousa.tcc

import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.ArrayAdapter
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.Spinner
import android.widget.AdapterView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.core.widget.addTextChangedListener
import androidx.lifecycle.lifecycleScope
import com.google.android.material.textfield.TextInputEditText
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class EnviarPromptActivity : AppCompatActivity() {

    private data class MateriaChip(val nome: String, val sigla: String)

    private lateinit var containerMaterias: LinearLayout
    private lateinit var etInstrucao: TextInputEditText
    private lateinit var btnGerarPreview: Button
    private lateinit var cardPreview: CardView
    private lateinit var txtPreviewPrompt: TextView
    private lateinit var progressBar: ProgressBar
    private lateinit var btnEnviar: Button
    private lateinit var spinnerLivro: Spinner
    private lateinit var spinnerBimestre: Spinner
    private var livros = listOf(0 to "Todos os livros da matéria")

    private var nomeProfessor = ""
    private var idTurma = 0
    private var nomeTurma = ""
    private var enviando = false

    private var materiaSelecionada: String? = null
    private var promptFinalAtual: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_enviar_prompt)
        NavegacaoUi.adicionarVoltar(this)

        if (!Sessao.ehProfessor(this)) { Sessao.encerrar(this); finish(); return }
        nomeProfessor = Sessao.nome(this)
        idTurma       = intent.getIntExtra("ID_TURMA", 0)
        nomeTurma     = intent.getStringExtra("NOME_TURMA") ?: "Turma"
        if (idTurma <= 0) { finish(); return }

        findViewById<TextView>(R.id.txtTituloPrompt).text = "Estudo de $nomeTurma"
        findViewById<TextView>(R.id.txtSubtituloPrompt).text =
            "A orientação será aplicada a todos os alunos da turma nesta matéria, até ser substituída."

        containerMaterias = findViewById(R.id.containerMaterias)
        etInstrucao       = findViewById(R.id.etInstrucaoProfessor)
        btnGerarPreview   = findViewById(R.id.btnGerarPreview)
        cardPreview       = findViewById(R.id.cardPreview)
        txtPreviewPrompt  = findViewById(R.id.txtPreviewPrompt)
        progressBar       = findViewById(R.id.progressBarEnviarPrompt)
        btnEnviar         = findViewById(R.id.btnEnviarPrompt)
        spinnerLivro      = findViewById(R.id.spinnerLivroPrompt)
        spinnerBimestre   = findViewById(R.id.spinnerBimestrePrompt)
        spinnerBimestre.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item,
            listOf("Sem bimestre", "1º bimestre", "2º bimestre", "3º bimestre", "4º bimestre"))
        spinnerLivro.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, livros.map { it.second })
        val aoSelecionar = object : AdapterView.OnItemSelectedListener {
            override fun onNothingSelected(parent: AdapterView<*>?) = Unit
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) = invalidarPreview()
        }
        spinnerLivro.onItemSelectedListener = aoSelecionar
        spinnerBimestre.onItemSelectedListener = aoSelecionar

        montarChipsMaterias()

        btnGerarPreview.setOnClickListener { gerarPreview() }
        btnEnviar.setOnClickListener { enviarPrompt() }

        // Qualquer alteração depois de gerar a prévia invalida o prompt já montado,
        // evitando enviar um texto que não reflete mais o que está na tela.
        etInstrucao.addTextChangedListener { invalidarPreview() }
    }

    private fun montarChipsMaterias() {
        containerMaterias.removeAllViews()
        progressBar.visibility = View.VISIBLE
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("listar_materias.php", mapOf("token" to Sessao.token(this@EnviarPromptActivity)))
            }
            progressBar.visibility = View.GONE
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@EnviarPromptActivity); finish(); return@launch }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                containerMaterias.addView(TextView(this@EnviarPromptActivity).apply {
                    text = "Não foi possível carregar as matérias. Toque para tentar novamente."
                    setOnClickListener { montarChipsMaterias() }
                })
                return@launch
            }
            val array = json.getJSONArray("materias")
            val materias = (0 until array.length()).mapNotNull { i ->
                val materia = array.getJSONObject(i)
                if (materia.optInt("capitulos_disponiveis") <= 0) null else {
                    val nome = materia.getString("nome")
                    MateriaChip(nome, MateriaVisual.sigla(nome))
                }
            }
            if (materias.isEmpty()) {
                containerMaterias.addView(TextView(this@EnviarPromptActivity).apply { text = "Nenhuma matéria com capítulos disponíveis." })
                return@launch
            }
            mostrarChipsMaterias(materias)
        }
    }

    private fun mostrarChipsMaterias(materias: List<MateriaChip>) {
        val chips = materias.map { materia ->
            TextView(this).apply {
                text = "${materia.sigla}  ${materia.nome}"
                textSize = 14f
                setPadding(EstudoUi.dp(this@EnviarPromptActivity, 15), EstudoUi.dp(this@EnviarPromptActivity, 11), EstudoUi.dp(this@EnviarPromptActivity, 15), EstudoUi.dp(this@EnviarPromptActivity, 11))
                setTextColor(UiPaleta.hex("#333333"))
                background = GradientDrawable().apply {
                    cornerRadius = EstudoUi.dp(this@EnviarPromptActivity, 12).toFloat()
                    setColor(UiPaleta.hex("#F0F0F5"))
                }
                layoutParams = ViewGroup.MarginLayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT
                ).apply { setMargins(0, 0, EstudoUi.dp(this@EnviarPromptActivity, 8), 0) }
                gravity = Gravity.CENTER
            }
        }

        chips.forEachIndexed { i, chip ->
            chip.setOnClickListener {
                if (enviando) return@setOnClickListener
                materiaSelecionada = materias[i].nome
                carregarLivros(materias[i].nome)
                chips.forEach {
                    it.setTextColor(UiPaleta.hex("#333333"))
                    (it.background as GradientDrawable).setColor(UiPaleta.hex("#F0F0F5"))
                }
                chip.setTextColor(Color.WHITE)
                (chip.background as GradientDrawable).setColor(UiPaleta.hex("#5C6BC0"))
                invalidarPreview()
            }
            containerMaterias.addView(chip)
        }
    }

    private fun carregarLivros(materia: String) {
        spinnerLivro.isEnabled = false
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("listar_livros_professor.php", mapOf("token" to Sessao.token(this@EnviarPromptActivity), "materia" to materia))
            }
            if (materiaSelecionada != materia) return@launch
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                Toast.makeText(this@EnviarPromptActivity, "Não foi possível carregar os livros.", Toast.LENGTH_SHORT).show()
                return@launch
            }
            val itens = json.getJSONArray("livros")
            livros = listOf(0 to "Todos os livros da matéria") + (0 until itens.length()).map { i ->
                val item = itens.getJSONObject(i)
                item.getInt("id_livro") to item.getString("titulo")
            }
            spinnerLivro.adapter = ArrayAdapter(this@EnviarPromptActivity, android.R.layout.simple_spinner_dropdown_item, livros.map { it.second })
            spinnerLivro.isEnabled = true
            invalidarPreview()
        }
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
            Toast.makeText(this, "Escreva o que a turma precisa reforçar.", Toast.LENGTH_SHORT).show()
            return
        }
        val livro = livros.getOrNull(spinnerLivro.selectedItemPosition)?.takeIf { it.first > 0 }
        val bimestre = spinnerBimestre.selectedItemPosition.takeIf { it > 0 }
        if (bimestre != null && livro == null) {
            Toast.makeText(this, "Escolha o livro deste bimestre.", Toast.LENGTH_SHORT).show()
            return
        }

        val prompt = PromptProfessorBuilder.montar(
            nomeTurma       = nomeTurma,
            nomeProfessor   = nomeProfessor,
            materia         = materia,
            instrucaoBruta  = instrucao,
            livro = livro?.second,
            bimestre = bimestre
        )

        promptFinalAtual = prompt
        txtPreviewPrompt.text = prompt
        cardPreview.visibility = View.VISIBLE
        btnEnviar.isEnabled = true
        btnEnviar.alpha = 1f
    }

    private fun enviarPrompt() {
        if (enviando || promptFinalAtual == null) return
        val materia = materiaSelecionada ?: return
        val instrucao = etInstrucao.text?.toString()?.trim().orEmpty()
        enviando = true
        progressBar.visibility = View.VISIBLE
        btnEnviar.isEnabled = false
        btnGerarPreview.isEnabled = false
        etInstrucao.isEnabled = false
        val parametros = mutableMapOf(
            "token" to Sessao.token(this), "id_turma" to idTurma.toString(),
            "materia" to materia, "instrucao" to instrucao
        )
        livros.getOrNull(spinnerLivro.selectedItemPosition)?.first?.takeIf { it > 0 }?.let { parametros["id_livro"] = it.toString() }
        spinnerBimestre.selectedItemPosition.takeIf { it > 0 }?.let { parametros["bimestre"] = it.toString() }
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("enviar_prompt_professor.php", parametros)
            }
            enviando = false
            progressBar.visibility = View.GONE
            btnEnviar.isEnabled = true
            btnGerarPreview.isEnabled = true
            etInstrucao.isEnabled = true
            if (resposta.sessaoExpirada) {
                Sessao.encerrar(this@EnviarPromptActivity); finish(); return@launch
            }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (resposta.sucesso && json?.optString("status") == "sucesso") {
                Toast.makeText(this@EnviarPromptActivity, "Orientação aplicada a todos os alunos de $nomeTurma.", Toast.LENGTH_LONG).show()
                finish()
            } else {
                Toast.makeText(this@EnviarPromptActivity, json?.optString("mensagem") ?: "Não foi possível salvar. Tente novamente.", Toast.LENGTH_LONG).show()
            }
        }
    }
}
