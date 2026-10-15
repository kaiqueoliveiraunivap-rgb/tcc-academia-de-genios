package com.github.carloseduardofsousa.tcc

import android.content.Intent
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
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.github.mikephil.charting.charts.BarChart
import com.github.mikephil.charting.components.XAxis
import com.github.mikephil.charting.data.BarData
import com.github.mikephil.charting.data.BarDataSet
import com.github.mikephil.charting.data.BarEntry
import com.github.mikephil.charting.formatter.IndexAxisValueFormatter
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray

// ── Modelo ───────────────────────────────────────────────────
data class AlunoDesempenho(
    val idAluno: Int,
    val nome: String,
    val pontosTotal: Int,
    val mesesParticipados: Int
)

// ── Adapter da lista de alunos ──────────────────────────────
class AlunoDesempenhoAdapter(
    private val items: List<AlunoDesempenho>
) : RecyclerView.Adapter<AlunoDesempenhoAdapter.VH>() {

    inner class VH(val card: LinearLayout) : RecyclerView.ViewHolder(card)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val ctx = parent.context

        val tvNome = TextView(ctx).apply {
            textSize = 16f
            setTextColor(ctx.getColor(R.color.TextoPrincipal))
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        val tvInfo = TextView(ctx).apply {
            textSize = 13f
            setTextColor(ctx.getColor(R.color.TextoSecundario))
            setPadding(0, 4, 0, 0)
        }
        val textos = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
            addView(tvNome)
            addView(tvInfo)
        }

        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(26, 22, 20, 22)
            layoutParams = ViewGroup.MarginLayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { setMargins(0, 0, 0, 12) }
            background = GradientDrawable().apply {
                cornerRadius = 16f
                setColor(UiPaleta.superficie())
                setStroke(1, ctx.getColor(R.color.BordaCard))
            }
            addView(textos)

        }
        return VH(card)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val aluno = items[position]

        val textos = h.card.getChildAt(0) as LinearLayout
        (textos.getChildAt(0) as TextView).text = aluno.nome
        (textos.getChildAt(1) as TextView).text =
            "${aluno.pontosTotal} pontos · ${aluno.mesesParticipados} mês(es) com participação"


    }

    override fun getItemCount() = items.size
}

// ── Activity ─────────────────────────────────────────────────
class TurmaDesempenhoActivity : AppCompatActivity() {

    private lateinit var progressBar: ProgressBar
    private lateinit var txtTitulo: TextView
    private lateinit var txtVazio: TextView
    private lateinit var scroll: View
    private lateinit var barChart: BarChart
    private lateinit var rvAlunos: RecyclerView

    private var idTurma = 0
    private var nomeProfessor = ""

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_turma_desempenho)
        NavegacaoUi.adicionarVoltar(this)

        progressBar = findViewById(R.id.progressBarDesempenho)
        txtTitulo   = findViewById(R.id.txtTituloTurma)
        txtVazio    = findViewById(R.id.txtVazioDesempenho)
        scroll      = findViewById(R.id.scrollDesempenho)
        barChart    = findViewById(R.id.barChartDesempenho)
        rvAlunos    = findViewById(R.id.rvAlunosDesempenho)
        rvAlunos.layoutManager = LinearLayoutManager(this)

        idTurma       = intent.getIntExtra("ID_TURMA", 0)
        nomeProfessor = intent.getStringExtra("NOME_PROFESSOR") ?: "Professor(a)"
        val nomeTurma = intent.getStringExtra("NOME_TURMA") ?: "Turma"

        txtTitulo.text = nomeTurma
        carregarDesempenho()
    }

    private fun carregarDesempenho() {
        progressBar.visibility = View.VISIBLE
        scroll.visibility = View.GONE
        txtVazio.visibility = View.GONE

        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus(
                    "listar_desempenho_turma.php",
                    mapOf(
                        "token" to Sessao.token(this@TurmaDesempenhoActivity),
                        "id_turma" to idTurma.toString()
                    )
                )
            }
            progressBar.visibility = View.GONE

            if (resposta.sessaoExpirada) {
                Toast.makeText(this@TurmaDesempenhoActivity, "Sessão expirada. Faça login novamente.", Toast.LENGTH_LONG).show()
                Sessao.encerrar(this@TurmaDesempenhoActivity)
                finish()
                return@launch
            }

            if (!resposta.sucesso) {
                txtVazio.text = "Não foi possível carregar o desempenho. Toque para tentar novamente."
                txtVazio.visibility = View.VISIBLE
                txtVazio.setOnClickListener { carregarDesempenho() }
                return@launch
            }

            val json = try { JSONArray(resposta.corpo) } catch (_: Exception) { JSONArray() }

            if (json.length() == 0) {
                txtVazio.text = "Ainda não há participação registrada para esta turma."
                txtVazio.visibility = View.VISIBLE
                txtVazio.setOnClickListener(null)
                return@launch
            }

            val alunos = (0 until json.length()).map { i ->
                val item = json.getJSONObject(i)
                AlunoDesempenho(
                    idAluno = item.getInt("id_usuario"),
                    nome = item.getString("nome"),
                    pontosTotal = item.getInt("pontos_total"),
                    mesesParticipados = item.getInt("meses_participados")
                )
            }

            scroll.visibility = View.VISIBLE
            montarGrafico(alunos)

            rvAlunos.adapter = AlunoDesempenhoAdapter(alunos)
        }
    }

    private fun montarGrafico(alunos: List<AlunoDesempenho>) {
        val entries = alunos.mapIndexed { i, a -> BarEntry(i.toFloat(), a.pontosTotal.toFloat()) }
        val primeirosNomes = alunos.map { it.nome.trim().split(" ").first() }

        val dataSet = BarDataSet(entries, "Pontos acumulados").apply {
            color = getColor(R.color.Destaque)
            valueTextColor = getColor(R.color.TextoPrincipal)
            valueTextSize = 11f
        }

        barChart.apply {
            data = BarData(dataSet).apply { barWidth = 0.55f }
            description.isEnabled = false
            legend.isEnabled = false
            setFitBars(true)
            setDrawGridBackground(false)
            setDrawBorders(false)
            setScaleEnabled(false)
            setPinchZoom(false)

            xAxis.apply {
                position = XAxis.XAxisPosition.BOTTOM
                valueFormatter = IndexAxisValueFormatter(primeirosNomes)
                granularity = 1f
                setDrawGridLines(false)
                textColor = getColor(R.color.TextoSecundario)
                labelRotationAngle = if (alunos.size > 4) -30f else 0f
            }
            axisLeft.apply {
                axisMinimum = 0f
                setDrawGridLines(true)
                gridColor = getColor(R.color.BordaCard)
                textColor = getColor(R.color.TextoSecundario)
            }
            axisRight.isEnabled = false

            animateY(700)
            invalidate()
        }
    }
}
