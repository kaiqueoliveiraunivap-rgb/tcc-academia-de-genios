package com.github.carloseduardofsousa.tcc

import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

// ── Modelo ───────────────────────────────────────────────────
data class MateriaItem(val nome: String, val emoji: String)

// ── Adapter do grid de matérias ──────────────────────────────
class MateriaAdapter(
    private val items: List<MateriaItem>,
    private val onClick: (MateriaItem) -> Unit
) : RecyclerView.Adapter<MateriaAdapter.VH>() {

    private var selectedPos = -1

    inner class VH(val card: LinearLayout) : RecyclerView.ViewHolder(card)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val ctx = parent.context
        val emoji = TextView(ctx).apply {
            textSize = 28f
            gravity = Gravity.CENTER
        }
        val label = TextView(ctx).apply {
            textSize = 13f
            gravity = Gravity.CENTER
            setPadding(4, 4, 4, 0)
            maxLines = 2
        }
        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(12, 20, 12, 20)
            layoutParams = ViewGroup.MarginLayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { setMargins(8, 8, 8, 8) }
            addView(emoji)
            addView(label)
        }
        return VH(card)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val item = items[position]
        val isSelected = position == selectedPos

        (h.card.getChildAt(0) as TextView).text = item.emoji
        (h.card.getChildAt(1) as TextView).apply {
            text = item.nome
            setTextColor(if (isSelected) Color.WHITE else Color.parseColor("#333333"))
        }
        h.card.background = GradientDrawable().apply {
            cornerRadius = 16f
            setColor(if (isSelected) Color.parseColor("#5C6BC0") else Color.parseColor("#F0F0F5"))
        }
        h.card.setOnClickListener {
            val pos = h.bindingAdapterPosition
            if (pos == RecyclerView.NO_POSITION) return@setOnClickListener

            val prev = selectedPos
            selectedPos = pos
            if (prev != RecyclerView.NO_POSITION) notifyItemChanged(prev)
            notifyItemChanged(selectedPos)
            onClick(items[pos])
        }
    }

    override fun getItemCount() = items.size
}

// ── Activity ─────────────────────────────────────────────────
class SelecionarMateriaActivity : AppCompatActivity() {

    private var materiaSelecionada: MateriaItem? = null
    private var dificuldade = "MEDIO"

    private lateinit var btnFacil:   Button
    private lateinit var btnMedio:   Button
    private lateinit var btnDificil: Button
    private lateinit var btnIniciar: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var tvCarregando: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_selecionar_materia)

        // Liga as views ao XML pelo ID
        btnFacil      = findViewById(R.id.btnFacil)
        btnMedio      = findViewById(R.id.btnMedio)
        btnDificil    = findViewById(R.id.btnDificil)
        btnIniciar    = findViewById(R.id.btnIniciar)
        progressBar   = findViewById(R.id.progressBar)
        tvCarregando  = findViewById(R.id.tvCarregando)

        val materias = listOf(
            MateriaItem("Matemática", "📐"),
            MateriaItem("História",   "📜"),
            MateriaItem("Geografia",  "🌍"),
            MateriaItem("Ciências",   "🔬"),
            MateriaItem("Português",  "📖"),
            MateriaItem("Inglês",     "🇺🇸"),
            MateriaItem("Física",     "⚛️"),
            MateriaItem("Química",    "🧪"),
            MateriaItem("Biologia",   "🧬"),
            MateriaItem("Filosofia",  "🤔"),
        )

        val rvMaterias = findViewById<RecyclerView>(R.id.rvMaterias)
        rvMaterias.layoutManager = GridLayoutManager(this, 2)
        rvMaterias.adapter = MateriaAdapter(materias) { item ->
            materiaSelecionada = item
            btnIniciar.isEnabled = true
            btnIniciar.alpha = 1f
        }

        btnFacil.setOnClickListener   { selecionarDificuldade("FACIL") }
        btnMedio.setOnClickListener   { selecionarDificuldade("MEDIO") }
        btnDificil.setOnClickListener { selecionarDificuldade("DIFICIL") }
        btnIniciar.setOnClickListener { iniciarQuiz() }

        selecionarDificuldade("MEDIO")
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
    }

    private fun iniciarQuiz() {
        val materia = materiaSelecionada ?: return

        progressBar.visibility  = View.VISIBLE
        tvCarregando.visibility = View.VISIBLE
        btnIniciar.isEnabled    = false
        btnIniciar.alpha        = 0.6f

        lifecycleScope.launch {
            val instrucaoProfessor = buscarInstrucaoProfessor()

            val resultado = GeminiService.gerarQuestoes(
                materia             = materia.nome,
                dificuldade         = dificuldade,
                quantidade          = 5,
                instrucaoProfessor  = instrucaoProfessor
            )

            progressBar.visibility  = View.GONE
            tvCarregando.visibility = View.GONE
            btnIniciar.isEnabled    = true
            btnIniciar.alpha        = 1f

            resultado.fold(
                onSuccess = { questoes ->
                    // TODO: quando criar a QuizActivity, descomente e ajuste:
                    // val intent = Intent(this@SelecionarMateriaActivity, QuizActivity::class.java)
                    // intent.putExtra("materia", materia.nome)
                    // intent.putExtra("dificuldade", dificuldade)
                    // startActivity(intent)

                    Toast.makeText(
                        this@SelecionarMateriaActivity,
                        "✅ ${questoes.size} questões geradas sobre ${materia.nome}!",
                        Toast.LENGTH_LONG
                    ).show()
                },
                onFailure = { erro ->
                    Toast.makeText(
                        this@SelecionarMateriaActivity,
                        "❌ Erro: ${erro.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            )
        }
    }

    /**
     * Verifica se algum professor deixou uma orientação personalizada pendente
     * para este aluno (tela "Enviar prompt IA" do professor) e, se houver,
     * usa o prompt já pronto para moldar as próximas questões da Gemini.
     */
    private suspend fun buscarInstrucaoProfessor(): String? {
        val token = Sessao.token(this)
        if (token.isEmpty()) return null

        return withContext(Dispatchers.IO) {
            // O aluno é identificado pelo token: o servidor não aceita mais um
            // id_aluno vindo do app, que permitiria consumir o prompt de outro.
            val resposta = ApiClient.post(
                "buscar_prompt_pendente.php",
                mapOf("token" to token)
            ) ?: return@withContext null

            try {
                val json = JSONObject(resposta)
                if (json.optString("status") == "sucesso") {
                    json.getJSONObject("prompt").getString("prompt_final")
                } else {
                    null
                }
            } catch (e: Exception) {
                null
            }
        }
    }
}
