package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.github.mikephil.charting.charts.BarChart
import com.github.mikephil.charting.charts.LineChart
import com.github.mikephil.charting.data.BarData
import com.github.mikephil.charting.data.BarDataSet
import com.github.mikephil.charting.data.BarEntry
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.LineData
import com.github.mikephil.charting.data.LineDataSet
import com.github.mikephil.charting.formatter.IndexAxisValueFormatter
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * TELA 1 — Visão geral das turmas do professor.
 *
 * Mostra os indicadores da rede, três gráficos (comparação entre turmas,
 * evolução das médias e aprovação) e a lista de turmas.
 *
 * A ordenação acontece só em memória: os dados são carregados uma vez e
 * reordenados na hora, sem nova chamada ao servidor.
 */
class DashboardTurmasActivity : AppCompatActivity() {

    private lateinit var repositorio: DashboardRepository

    private lateinit var progressBar: ProgressBar
    private lateinit var scroll: ScrollView
    private lateinit var txtVazio: TextView
    private lateinit var txtSubtitulo: TextView
    private lateinit var txtKpiTurmas: TextView
    private lateinit var txtKpiAlunos: TextView
    private lateinit var txtKpiMedia: TextView
    private lateinit var txtKpiAprovacao: TextView
    private lateinit var txtLegendaComparativo: TextView
    private lateinit var chartComparativo: BarChart
    private lateinit var chartEvolucao: LineChart
    private lateinit var chartAprovacao: BarChart
    private lateinit var containerOrdenacao: LinearLayout
    private lateinit var rvTurmas: RecyclerView

    private var visaoGeral: VisaoGeral? = null
    private var ordenacao = OrdenacaoTurmas.MAIOR_MEDIA
    private var adapter: TurmaResumoAdapter? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Primeira barreira (a que vale é a do servidor): sem sessão de
        // professor, esta tela nem chega a ser montada.
        if (!Sessao.ehProfessor(this)) {
            Toast.makeText(this, "Faça login como professor para ver o dashboard.", Toast.LENGTH_LONG).show()
            Sessao.encerrar(this)
            finish()
            return
        }

        setContentView(R.layout.activity_dashboard_turmas)
        repositorio = DashboardRepository(this)

        progressBar           = findViewById(R.id.progressBarDashboard)
        scroll                = findViewById(R.id.scrollDashboard)
        txtVazio              = findViewById(R.id.txtVazioDashboard)
        txtSubtitulo          = findViewById(R.id.txtSubtituloDashboard)
        txtKpiTurmas          = findViewById(R.id.txtKpiTurmas)
        txtKpiAlunos          = findViewById(R.id.txtKpiAlunos)
        txtKpiMedia           = findViewById(R.id.txtKpiMedia)
        txtKpiAprovacao       = findViewById(R.id.txtKpiAprovacao)
        txtLegendaComparativo = findViewById(R.id.txtLegendaComparativo)
        chartComparativo      = findViewById(R.id.chartComparativo)
        chartEvolucao         = findViewById(R.id.chartEvolucao)
        chartAprovacao        = findViewById(R.id.chartAprovacao)
        containerOrdenacao    = findViewById(R.id.containerOrdenacao)
        rvTurmas              = findViewById(R.id.rvTurmasDashboard)

        rvTurmas.layoutManager = LinearLayoutManager(this)
        txtSubtitulo.text = "Olá, ${Sessao.nome(this)}"

        findViewById<Button>(R.id.btnSairDashboard).setOnClickListener { Sessao.encerrar(this) }

        montarChipsOrdenacao()
    }

    /** Recarrega ao voltar da Tela 2: uma nota editada lá reflete aqui. */
    override fun onResume() {
        super.onResume()
        if (Sessao.estaLogado(this)) carregar()
    }

    // ── Carregamento ────────────────────────────────────────────────────────

    private fun carregar() {
        progressBar.visibility = View.VISIBLE
        txtVazio.visibility = View.GONE

        lifecycleScope.launch {
            val resultado = withContext(Dispatchers.IO) { repositorio.carregarVisaoGeral() }

            progressBar.visibility = View.GONE

            when (resultado) {
                is ResultadoApi.SessaoExpirada -> tratarSessaoExpirada()
                is ResultadoApi.Erro -> {
                    scroll.visibility = View.GONE
                    txtVazio.visibility = View.VISIBLE
                    txtVazio.text = resultado.mensagem
                }
                is ResultadoApi.Sucesso -> {
                    visaoGeral = resultado.dados
                    exibir(resultado.dados)
                }
            }
        }
    }

    private fun tratarSessaoExpirada() {
        Toast.makeText(this, "Sua sessão expirou. Entre novamente.", Toast.LENGTH_LONG).show()
        Sessao.encerrar(this)
        finish()
    }

    private fun exibir(dados: VisaoGeral) {
        if (dados.turmas.isEmpty()) {
            scroll.visibility = View.GONE
            txtVazio.visibility = View.VISIBLE
            txtVazio.text = "Você ainda não tem turmas cadastradas."
            return
        }

        scroll.visibility = View.VISIBLE
        txtVazio.visibility = View.GONE

        txtKpiTurmas.text    = dados.turmas.size.toString()
        txtKpiAlunos.text    = dados.totalAlunos.toString()
        txtKpiMedia.text     = Formato.nota(dados.mediaGeral)
        txtKpiAprovacao.text = Formato.percentual(dados.percentualAprovacaoGeral)

        aplicarOrdenacao()
        montarGraficoEvolucao(dados.turmas)
    }

    // ── Ordenação ───────────────────────────────────────────────────────────

    private fun montarChipsOrdenacao() {
        val opcoes = OrdenacaoTurmas.values().map { OpcaoFiltro(it.name, it.rotulo) }

        ChipFiltro.montar(containerOrdenacao, opcoes, ordenacao.name) { id ->
            ordenacao = OrdenacaoTurmas.valueOf(id)
            // Reordena a lista já carregada: lista e gráficos mudam na hora.
            aplicarOrdenacao()
        }
    }

    /**
     * Aplica a ordenação escolhida à lista e aos gráficos que comparam turmas,
     * para que a ordem das barras acompanhe a ordem dos cards.
     */
    private fun aplicarOrdenacao() {
        val turmas = visaoGeral?.turmas ?: return
        val ordenadas = ordenacao.aplicar(turmas)

        if (adapter == null) {
            adapter = TurmaResumoAdapter(ordenadas) { abrirAnalise(it) }
            rvTurmas.adapter = adapter
        } else {
            adapter?.atualizar(ordenadas)
        }

        montarGraficoComparativo(ordenadas)
        montarGraficoAprovacao(ordenadas)
    }

    private fun abrirAnalise(turma: TurmaResumo) {
        val intent = Intent(this, TurmaAnaliseActivity::class.java)
        intent.putExtra(TurmaAnaliseActivity.EXTRA_ID_TURMA, turma.idTurma)
        intent.putExtra(TurmaAnaliseActivity.EXTRA_NOME_TURMA, turma.nomeTurma)
        startActivity(intent)
    }

    // ── Gráficos ────────────────────────────────────────────────────────────

    /** Comparação direta entre as turmas — o gráfico principal da tela. */
    private fun montarGraficoComparativo(turmas: List<TurmaResumo>) {
        val entradas = turmas.mapIndexed { i, t -> BarEntry(i.toFloat(), t.mediaGeral.toFloat()) }
        val rotulos  = turmas.map { it.nomeTurma }

        val dataSet = BarDataSet(entradas, "Média").apply {
            color = GraficoEstilo.cor(this@DashboardTurmasActivity, R.color.Destaque)
            valueTextColor = GraficoEstilo.cor(this@DashboardTurmasActivity, R.color.TextoPrincipal)
            valueTextSize = 11f
            valueFormatter = GraficoEstilo.formatadorNota()
        }

        GraficoEstilo.aplicarBarras(
            chart = chartComparativo,
            rotulos = rotulos,
            rotacionarRotulos = true,
            maximoEixoY = 10f
        )
        GraficoEstilo.aoTocar(chartComparativo) { indice, valor ->
            turmas.getOrNull(indice)?.let {
                txtLegendaComparativo.text =
                    "${it.nomeTurma}: média ${Formato.nota(valor.toDouble())} · " +
                    "${it.totalAlunos} alunos · ${Formato.percentual(it.percentualAprovacao)} na média"
            }
        }

        chartComparativo.data = BarData(dataSet).apply { barWidth = 0.55f }
        chartComparativo.invalidate()
        chartComparativo.animateY(700)
    }

    /** Uma linha por turma, para comparar as trajetórias no mesmo eixo. */
    private fun montarGraficoEvolucao(turmas: List<TurmaResumo>) {
        // Eixo X comum: todos os períodos existentes, em ordem.
        val periodos = turmas.flatMap { it.evolucao }
            .distinctBy { it.periodo }
            .sortedBy { it.periodo }

        if (periodos.isEmpty()) {
            chartEvolucao.clear()
            chartEvolucao.invalidate()
            return
        }

        val rotulos = periodos.map { it.rotulo }
        val cores   = GraficoEstilo.cores(this)

        val linhas = turmas.filter { it.evolucao.isNotEmpty() }.mapIndexed { indice, turma ->
            val entradas = turma.evolucao.mapNotNull { ponto ->
                val x = periodos.indexOfFirst { it.periodo == ponto.periodo }
                if (x < 0) null else Entry(x.toFloat(), ponto.media.toFloat())
            }.sortedBy { it.x }

            LineDataSet(entradas, turma.nomeTurma).apply {
                val cor = cores[indice % cores.size]
                color = cor
                setCircleColor(cor)
                lineWidth = 2.2f
                circleRadius = 4f
                setDrawCircleHole(false)
                setDrawValues(false)
                mode = LineDataSet.Mode.CUBIC_BEZIER
            }
        }

        GraficoEstilo.aplicarLinhas(chartEvolucao, rotulos)
        chartEvolucao.data = LineData(linhas)
        chartEvolucao.invalidate()
        chartEvolucao.animateY(700)
    }

    /** Barras empilhadas: quanto da turma está na média e quanto está abaixo. */
    private fun montarGraficoAprovacao(turmas: List<TurmaResumo>) {
        val entradas = turmas.mapIndexed { i, t ->
            BarEntry(i.toFloat(), floatArrayOf(
                t.percentualAprovacao.toFloat(),
                t.percentualAbaixo.toFloat()
            ))
        }

        val dataSet = BarDataSet(entradas, "").apply {
            setColors(
                GraficoEstilo.cor(this@DashboardTurmasActivity, R.color.OptCerta),
                GraficoEstilo.cor(this@DashboardTurmasActivity, R.color.OptErrada)
            )
            stackLabels = arrayOf("Na média", "Abaixo da média")
            setDrawValues(false)
        }

        GraficoEstilo.aplicarBarras(
            chart = chartAprovacao,
            rotulos = turmas.map { it.nomeTurma },
            comLegenda = true,
            rotacionarRotulos = true,
            maximoEixoY = 100f
        )
        chartAprovacao.axisLeft.valueFormatter = object :
            com.github.mikephil.charting.formatter.ValueFormatter() {
            override fun getFormattedValue(value: Float) = "${value.toInt()}%"
        }
        GraficoEstilo.aoTocar(chartAprovacao) { indice, _ ->
            turmas.getOrNull(indice)?.let {
                findViewById<TextView>(R.id.txtLegendaAprovacao).text =
                    "${it.nomeTurma}: ${Formato.percentual(it.percentualAprovacao)} na média · " +
                    "${Formato.percentual(it.percentualAbaixo)} abaixo da média"
            }
        }

        chartAprovacao.xAxis.valueFormatter = IndexAxisValueFormatter(turmas.map { it.nomeTurma })
        chartAprovacao.data = BarData(dataSet).apply { barWidth = 0.5f }
        chartAprovacao.invalidate()
        chartAprovacao.animateY(700)
    }
}
