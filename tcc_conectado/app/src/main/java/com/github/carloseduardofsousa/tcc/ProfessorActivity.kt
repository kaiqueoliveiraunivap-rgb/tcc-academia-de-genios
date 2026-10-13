package com.github.carloseduardofsousa.tcc

import android.content.Intent
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
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

// ── Modelo ───────────────────────────────────────────────────
data class TurmaItem(
    val idTurma: Int,
    val nomeTurma: String,
    val serie: String,
    val anoLetivo: String,
    val totalAlunos: Int,
    val atividades: Int
)

// ── Adapter da lista de turmas ──────────────────────────────
class TurmaAdapter(
    private val items: List<TurmaItem>,
    private val onClick: (TurmaItem) -> Unit
) : RecyclerView.Adapter<TurmaAdapter.VH>() {

    inner class VH(val card: LinearLayout) : RecyclerView.ViewHolder(card)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val ctx = parent.context

        val tvNome = TextView(ctx).apply {
            id = View.generateViewId()
            textSize = 17f
            setTextColor(UiPaleta.hex("#1A237E"))
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        val tvInfo = TextView(ctx).apply {
            id = View.generateViewId()
            textSize = 13f
            setTextColor(UiPaleta.hex("#666680"))
            setPadding(0, 4, 0, 0)
        }
        val textos = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
            addView(tvNome)
            addView(tvInfo)
        }
        val seta = TextView(ctx).apply {
            text = "›"
            textSize = 24f
            setTextColor(UiPaleta.hex("#9FA8DA"))
            gravity = Gravity.CENTER
        }

        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            val d=ctx.resources.displayMetrics.density
            setPadding((18*d).toInt(),(17*d).toInt(),(18*d).toInt(),(17*d).toInt())
            layoutParams = ViewGroup.MarginLayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { setMargins(0, 0, 0, (11*d).toInt()) }
            background = GradientDrawable().apply {
                cornerRadius = 16*d
                setColor(UiPaleta.superficie())
            }
            addView(textos)
            addView(seta)
        }
        return VH(card)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val item = items[position]
        val textos = h.card.getChildAt(0) as LinearLayout
        (textos.getChildAt(0) as TextView).text = item.nomeTurma
        (textos.getChildAt(1) as TextView).text =
            "${item.serie} · ${item.anoLetivo} · ${item.totalAlunos} alunos · ${item.atividades} atividades"
        h.card.setOnClickListener { onClick(item) }
    }

    override fun getItemCount() = items.size
}

// ── Activity ─────────────────────────────────────────────────
class ProfessorActivity : AppCompatActivity() {

    private lateinit var progressBar: ProgressBar
    private lateinit var txtSaudacao: TextView
    private lateinit var txtVazio: TextView
    private lateinit var rvTurmas: RecyclerView

    private var nomeProfessor = ""

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_professor)
        NavegacaoUi.adicionarVoltar(this)

        // Sem sessão de professor esta tela não abre. A checagem que vale é a
        // do servidor, que confere o token a cada requisição; esta aqui só
        // evita mostrar uma tela vazia para quem não deveria estar aqui.
        if (!Sessao.ehProfessor(this)) {
            Toast.makeText(this, "Faça login como professor para continuar.", Toast.LENGTH_LONG).show()
            Sessao.encerrar(this)
            finish()
            return
        }

        progressBar  = findViewById(R.id.progressBarProfessor)
        txtSaudacao  = findViewById(R.id.txtSaudacaoProfessor)
        txtVazio     = findViewById(R.id.txtVazioProfessor)
        rvTurmas     = findViewById(R.id.rvTurmas)
        rvTurmas.layoutManager = LinearLayoutManager(this)

        // O nome vem da sessão salva no login: assim a tela continua correta
        // mesmo quando o Android recria a Activity sem os extras do Intent.
        nomeProfessor = Sessao.nome(this)

        txtSaudacao.text = "Olá, $nomeProfessor"

        findViewById<Button>(R.id.btnAbrirDashboard).setOnClickListener {
            startActivity(Intent(this, DashboardTurmasActivity::class.java))
        }
        findViewById<Button>(R.id.btnSairProfessor).setOnClickListener {
            Sessao.encerrar(this)
            finish()
        }

    }

    override fun onResume() {
        super.onResume()
        if (::rvTurmas.isInitialized) carregarTurmas()
    }

    private fun carregarTurmas() {
        progressBar.visibility = View.VISIBLE
        txtVazio.visibility = View.GONE

        lifecycleScope.launch {
            // Quem é o professor é resolvido pelo servidor a partir do token;
            // o id não é mais enviado porque o backend o ignoraria de qualquer forma.
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("professor_pedagogico.php",
                    mapOf("token" to Sessao.token(this@ProfessorActivity),"acao" to "resumo"))
            }
                progressBar.visibility = View.GONE

                if (resposta.sessaoExpirada) {
                    Toast.makeText(this@ProfessorActivity, "Sessão expirada. Faça login novamente.", Toast.LENGTH_LONG).show()
                    Sessao.encerrar(this@ProfessorActivity)
                    finish()
                    return@launch
                }

                if (!resposta.sucesso) {
                    txtVazio.text = resposta.mensagemErro("Não foi possível carregar as turmas. Toque para tentar novamente.")
                    txtVazio.visibility = View.VISIBLE
                    txtVazio.setOnClickListener { carregarTurmas() }
                    return@launch
                }

                txtVazio.setOnClickListener(null)

                val dados = try { JSONObject(resposta.corpo.orEmpty()) } catch (e: Exception) { JSONObject() }
                val json = dados.optJSONArray("turmas") ?: JSONArray()
                val media=if (dados.isNull("aproveitamento_medio")) "Sem quizzes ainda"
                    else "${dados.optDouble("aproveitamento_medio").toInt()}% de aproveitamento nos quizzes"
                findViewById<TextView>(R.id.txtIndicadoresProfessor).text =
                    "${dados.optInt("turmas_total")} turmas · ${dados.optInt("alunos_total")} alunos\n"+
                    "${dados.optInt("atividades_publicadas")} atividades publicadas · ${dados.optInt("atividades_concluidas")} conclusões\n$media"

                if (json.length() == 0) {
                    txtVazio.text = "Você ainda não tem turmas vinculadas. Procure a secretaria."
                    txtVazio.visibility = View.VISIBLE
                    return@launch
                }

                val turmas = (0 until json.length()).map { i ->
                    val item = json.getJSONObject(i)
                    TurmaItem(
                        idTurma     = item.getInt("id_turma"),
                        nomeTurma   = item.getString("nome_turma"),
                        serie       = item.optString("serie"),
                        anoLetivo   = item.getString("ano_letivo"),
                        totalAlunos = item.getInt("alunos"),
                        atividades  = item.getInt("atividades")
                    )
                }

                rvTurmas.adapter = TurmaAdapter(turmas) { turma ->
                    val intent = Intent(this@ProfessorActivity, ProfessorConteudosActivity::class.java)
                    intent.putExtra("ID_TURMA", turma.idTurma)
                    intent.putExtra("NOME_TURMA", turma.nomeTurma)
                    intent.putExtra("TOTAL_ALUNOS", turma.totalAlunos)
                    intent.putExtra("NOME_PROFESSOR", nomeProfessor)
                    startActivity(intent)
                }
        }
    }
}
