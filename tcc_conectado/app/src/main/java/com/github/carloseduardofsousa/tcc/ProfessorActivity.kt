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
import org.json.JSONArray

// ── Modelo ───────────────────────────────────────────────────
data class TurmaItem(
    val idTurma: Int,
    val nomeTurma: String,
    val anoLetivo: String,
    val totalAlunos: Int
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
            setTextColor(Color.parseColor("#1A237E"))
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        val tvInfo = TextView(ctx).apply {
            id = View.generateViewId()
            textSize = 13f
            setTextColor(Color.parseColor("#666680"))
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
            setTextColor(Color.parseColor("#9FA8DA"))
            gravity = Gravity.CENTER
        }

        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(28, 26, 28, 26)
            layoutParams = ViewGroup.MarginLayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { setMargins(0, 0, 0, 14) }
            background = GradientDrawable().apply {
                cornerRadius = 18f
                setColor(Color.WHITE)
            }
            addView(textos)
            addView(seta)
        }
        return VH(card)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val item = items[position]
        val textos = h.card.getChildAt(0) as LinearLayout
        (textos.getChildAt(0) as TextView).text = "👥  ${item.nomeTurma}"
        (textos.getChildAt(1) as TextView).text =
            "Ano letivo ${item.anoLetivo} · ${item.totalAlunos} aluno${if (item.totalAlunos == 1) "" else "s"}"
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

        carregarTurmas()
    }

    private fun carregarTurmas() {
        progressBar.visibility = View.VISIBLE
        txtVazio.visibility = View.GONE

        Thread {
            // Quem é o professor é resolvido pelo servidor a partir do token;
            // o id não é mais enviado porque o backend o ignoraria de qualquer forma.
            val resposta = ApiClient.postComStatus(
                "listar_turmas_professor.php",
                mapOf("token" to Sessao.token(this))
            )

            runOnUiThread {
                progressBar.visibility = View.GONE

                if (resposta.sessaoExpirada) {
                    Toast.makeText(this, "Sessão expirada. Faça login novamente.", Toast.LENGTH_LONG).show()
                    Sessao.encerrar(this)
                    finish()
                    return@runOnUiThread
                }

                if (!resposta.sucesso) {
                    Toast.makeText(this, "Erro ao carregar suas turmas.", Toast.LENGTH_LONG).show()
                    return@runOnUiThread
                }

                val json = try { JSONArray(resposta.corpo) } catch (e: Exception) { JSONArray() }

                if (json.length() == 0) {
                    txtVazio.visibility = View.VISIBLE
                    return@runOnUiThread
                }

                val turmas = (0 until json.length()).map { i ->
                    val item = json.getJSONObject(i)
                    TurmaItem(
                        idTurma     = item.getInt("id_turma"),
                        nomeTurma   = item.getString("nome_turma"),
                        anoLetivo   = item.getString("ano_letivo"),
                        totalAlunos = item.getInt("total_alunos")
                    )
                }

                rvTurmas.adapter = TurmaAdapter(turmas) { turma ->
                    val intent = Intent(this, TurmaDesempenhoActivity::class.java)
                    intent.putExtra("ID_TURMA", turma.idTurma)
                    intent.putExtra("NOME_TURMA", turma.nomeTurma)
                    intent.putExtra("NOME_PROFESSOR", nomeProfessor)
                    startActivity(intent)
                }
            }
        }.start()
    }
}
