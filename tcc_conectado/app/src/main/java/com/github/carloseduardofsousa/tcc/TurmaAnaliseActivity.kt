package com.github.carloseduardofsousa.tcc

import android.os.Bundle
import android.view.View
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
import com.github.mikephil.charting.charts.PieChart
import com.github.mikephil.charting.data.BarData
import com.github.mikephil.charting.data.BarDataSet
import com.github.mikephil.charting.data.BarEntry
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.LineData
import com.github.mikephil.charting.data.LineDataSet
import com.github.mikephil.charting.data.PieData
import com.github.mikephil.charting.data.PieDataSet
import com.github.mikephil.charting.data.PieEntry
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * TELA 2 — Análise detalhada de uma turma.
 *
 * O professor escolhe turma, disciplina, período e avaliação; a cada troca de
 * filtro os dados são recarregados e TODOS os gráficos e indicadores da tela
 * se atualizam a partir da mesma resposta.
 *
 * A lista de turmas do seletor vem de dashboard_turmas.php, ou seja, só traz
 * turmas do professor logado. Ainda assim, o detalhe de cada turma é validado
 * de novo no servidor a cada requisição.
 */
class TurmaAnaliseActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_ID_TURMA   = "ID_TURMA"
        const val EXTRA_NOME_TURMA = "NOME_TURMA"
    }

    private lateinit var repositorio: DashboardRepository

    private lateinit var progressBar: ProgressBar
    private lateinit var scroll: ScrollView
    private lateinit var txtErro: TextView
    private lateinit var txtTitulo: TextView
    private lateinit var txtSubtitulo: TextView

    private lateinit var txtAlunos: TextView
    private lateinit var txtMedia: TextView
    private lateinit var txtMaior: TextView
    private lateinit var txtMenor: TextView

    private lateinit var chartAprovacao: PieChart
    private lateinit var chartDistribuicao: BarChart
    private lateinit var chartAvaliacoes: BarChart
    private lateinit var chartEvolucao: LineChart

    private lateinit var containerTurma: LinearLayout
    private lateinit var containerDisciplina: LinearLayout
    private lateinit var containerPeriodo: LinearLayout
    private lateinit var containerAvaliacao: LinearLayout

    private lateinit var rvAlunos: RecyclerView
    private lateinit var txtVazioAlunos: TextView

    private var idTurma = 0
    private var filtro = FiltroAnalise()
    private var turmasDoProfessor: List<OpcaoFiltro> = emptyList()
    private var detalheAtual: TurmaDetalhe? = null
    private var adapter: AlunoAnaliseAdapter? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (!Sessao.ehProfessor(this)) {
            Toast.makeText(this, "Faça login como professor para ver a análise.", Toast.LENGTH_LONG).show()
            Sessao.encerrar(this)
            finish()
            return
        }

        setContentView(R.layout.activity_turma_analise)
        repositorio = DashboardRepository(this)

        idTurma = intent.getIntExtra(EXTRA_ID_TURMA, 0)
        if (idTurma <= 0) {
            Toast.makeText(this, "Turma não informada.", Toast.LENGTH_LONG).show()
            finish()
            return
        }

        progressBar   = findViewById(R.id.progressBarAnalise)
        scroll        = findViewById(R.id.scrollAnalise)
        txtErro       = findViewById(R.id.txtErroAnalise)
        txtTitulo     = findViewById(R.id.txtTituloAnalise)
        txtSubtitulo  = findViewById(R.id.txtSubtituloAnalise)

        txtAlunos = findViewById(R.id.txtAnaliseAlunos)
        txtMedia  = findViewById(R.id.txtAnaliseMedia)
        txtMaior  = findViewById(R.id.txtAnaliseMaior)
        txtMenor  = findViewById(R.id.txtAnaliseMenor)

        chartAprovacao    = findViewById(R.id.chartAprovacaoAnalise)
        chartDistribuicao = findViewById(R.id.chartDistribuicao)
        chartAvaliacoes   = findViewById(R.id.chartAvaliacoes)
        chartEvolucao     = findViewById(R.id.chartEvolucaoAnalise)

        containerTurma      = findViewById(R.id.containerFiltroTurma)
        containerDisciplina = findViewById(R.id.containerFiltroDisciplina)
        containerPeriodo    = findViewById(R.id.containerFiltroPeriodo)
        containerAvaliacao  = findViewById(R.id.containerFiltroAvaliacao)

        rvAlunos = findViewById(R.id.rvAlunosAnalise)
        rvAlunos.layoutManager = LinearLayoutManager(this)
        txtVazioAlunos = findViewById(R.id.txtVazioAlunos)

        txtTitulo.text = intent.getStringExtra(EXTRA_NOME_TURMA) ?: "Turma"

        carregarTurmasDoProfessor()
        carregar()
    }

    // ── Carregamento ────────────────────────────────────────────────────────

    /** Alimenta o seletor de turmas com as turmas do professor logado. */
    private fun carregarTurmasDoProfessor() {
        lifecycleScope.launch {
            val resultado = withContext(Dispatchers.IO) { repositorio.carregarVisaoGeral() }
            if (resultado is ResultadoApi.Sucesso) {
                turmasDoProfessor = resultado.dados.turmas.map {
                    OpcaoFiltro(it.idTurma.toString(), it.nomeTurma)
                }
                montarFiltroTurma()
            }
        }
    }

    private fun carregar() {
        progressBar.visibility = View.VISIBLE
        txtErro.visibility = View.GONE

        lifecycleScope.launch {
            val resultado = withContext(Dispatchers.IO) {
                repositorio.carregarDetalhe(idTurma, filtro)
            }

            progressBar.visibility = View.GONE

            when (resultado) {
                is ResultadoApi.SessaoExpirada -> {
                    Toast.makeText(
                        this@TurmaAnaliseActivity,
                        "Sua sessão expirou. Entre novamente.",
                        Toast.LENGTH_LONG
                    ).show()
                    Sessao.encerrar(this@TurmaAnaliseActivity)
                    finish()
                }
                is ResultadoApi.Erro -> {
                    scroll.visibility = View.GONE
                    txtErro.visibility = View.VISIBLE
                    txtErro.text = resultado.mensagem
                }
                is ResultadoApi.Sucesso -> {
                    detalheAtual = resultado.dados
                    exibir(resultado.dados)
                }
            }
        }
    }

    private fun exibir(detalhe: TurmaDetalhe) {
        scroll.visibility = View.VISIBLE
        txtErro.visibility = View.GONE

        txtTitulo.text = detalhe.nomeTurma
        txtSubtitulo.text = "Ano letivo ${detalhe.anoLetivo} · " +
                "aprovação a partir de ${Formato.nota(detalhe.mediaAprovacao)}"

        montarFiltros(detalhe)
        montarIndicadores(detalhe)
        montarGraficoAprovacao(detalhe)
        montarGraficoDistribuicao(detalhe)
        montarGraficoAvaliacoes(detalhe)
        montarGraficoEvolucao(detalhe)
        montarListaAlunos(detalhe)
    }

    // ── Filtros ─────────────────────────────────────────────────────────────

    private fun montarFiltroTurma() {
        if (turmasDoProfessor.isEmpty()) return

        ChipFiltro.montar(containerTurma, turmasDoProfessor, idTurma.toString()) { id ->
            val novoId = id.toIntOrNull() ?: return@montar
            if (novoId == idTurma) return@montar

            idTurma = novoId
            // Filtros de disciplina/período/avaliação são de outra turma: zerar.
            filtro = FiltroAnalise()
            carregar()
        }
    }

    private fun montarFiltros(detalhe: TurmaDetalhe) {
        montarFiltroTurma()

        val todas = OpcaoFiltro("", "Todas")

        montarLinhaFiltro(
            container = containerDisciplina,
            label = findViewById(R.id.labelFiltroDisciplina),
            scrollView = findViewById(R.id.scrollFiltroDisciplina),
            opcoes = listOf(todas) + detalhe.filtros.materias,
            selecionado = filtro.idMateria
        ) { filtro = filtro.copy(idMateria = it) }

        montarLinhaFiltro(
            container = containerPeriodo,
            label = findViewById(R.id.labelFiltroPeriodo),
            scrollView = findViewById(R.id.scrollFiltroPeriodo),
            opcoes = listOf(OpcaoFiltro("", "Todos")) + detalhe.filtros.periodos,
            selecionado = filtro.periodo
        ) { filtro = filtro.copy(periodo = it) }

        montarLinhaFiltro(
            container = containerAvaliacao,
            label = findViewById(R.id.labelFiltroAvaliacao),
            scrollView = findViewById(R.id.scrollFiltroAvaliacao),
            opcoes = listOf(todas) + detalhe.filtros.avaliacoes,
            selecionado = filtro.idAvaliacao
        ) { filtro = filtro.copy(idAvaliacao = it) }
    }

    /**
     * Monta uma linha de filtro. Quando a turma não tem aquela informação
     * (por exemplo, avaliações sem disciplina cadastrada), a linha inteira é
     * escondida em vez de aparecer com um único chip "Todas" inútil.
     */
    private fun montarLinhaFiltro(
        container: LinearLayout,
        label: TextView,
        scrollView: View,
        opcoes: List<OpcaoFiltro>,
        selecionado: String,
        aoEscolher: (String) -> Unit
    ) {
        val temOpcoes = opcoes.size > 1
        label.visibility = if (temOpcoes) View.VISIBLE else View.GONE
        scrollView.visibility = if (temOpcoes) View.VISIBLE else View.GONE
        if (!temOpcoes) return

        ChipFiltro.montar(container, opcoes, selecionado) { id ->
            aoEscolher(id)
            carregar()
        }
    }

    // ── Indicadores e gráficos ──────────────────────────────────────────────

    private fun montarIndicadores(detalhe: TurmaDetalhe) {
        val resumo = detalhe.resumo

        txtAlunos.text = detalhe.totalAlunos.toString()

        if (detalhe.semDados) {
            txtMedia.text = "—"
            txtMaior.text = "—"
            txtMenor.text = "—"
        } else {
            txtMedia.text = Formato.nota(resumo.mediaTurma)
            txtMaior.text = Formato.nota(resumo.maiorNota)
            txtMenor.text = Formato.nota(resumo.menorNota)
        }
    }

    private fun montarGraficoAprovacao(detalhe: TurmaDetalhe) {
        val resumo = detalhe.resumo

        if (detalhe.semDados) {
            chartAprovacao.clear()
            chartAprovacao.invalidate()
            findViewById<TextView>(R.id.txtLegendaAprovacaoAnalise).text =
                "Sem notas para os filtros escolhidos."
            return
        }

        val entradas = listOf(
            PieEntry(resumo.aprovados.toFloat(), "Na média"),
            PieEntry(resumo.reprovados.toFloat(), "Abaixo da média")
        ).filter { it.value > 0f }

        val dataSet = PieDataSet(entradas, "").apply {
            colors = listOf(
                GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.OptCerta),
                GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.OptErrada)
            ).take(entradas.size)
            valueTextColor = android.graphics.Color.WHITE
            valueTextSize = 13f
            sliceSpace = 2f
            valueFormatter = GraficoEstilo.formatadorInteiro()
        }

        GraficoEstilo.aplicarPizza(chartAprovacao)
        chartAprovacao.centerText = Formato.percentual(resumo.percentualAprovacao)
        chartAprovacao.data = PieData(dataSet)
        chartAprovacao.invalidate()
        chartAprovacao.animateY(700)

        findViewById<TextView>(R.id.txtLegendaAprovacaoAnalise).text =
            "${resumo.aprovados} na média · ${resumo.reprovados} abaixo · " +
            "${resumo.totalAvaliados} aluno${if (resumo.totalAvaliados == 1) "" else "s"} avaliados"
    }

    private fun montarGraficoDistribuicao(detalhe: TurmaDetalhe) {
        if (detalhe.distribuicao.isEmpty()) {
            chartDistribuicao.clear()
            chartDistribuicao.invalidate()
            return
        }

        val entradas = detalhe.distribuicao.mapIndexed { i, faixa ->
            BarEntry(i.toFloat(), faixa.quantidade.toFloat())
        }

        // A faixa abaixo da média fica em vermelho: identifica o problema de longe.
        val cores = detalhe.distribuicao.map { faixa ->
            val limiteInferior = faixa.rotulo.substringBefore(" a ").toDoubleOrNull() ?: 0.0
            if (limiteInferior + 2 <= detalhe.mediaAprovacao) {
                GraficoEstilo.cor(this, R.color.OptErrada)
            } else {
                GraficoEstilo.cor(this, R.color.Destaque)
            }
        }

        val dataSet = BarDataSet(entradas, "Alunos por faixa").apply {
            setColors(cores)
            valueTextColor = GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.TextoPrincipal)
            valueTextSize = 11f
            valueFormatter = GraficoEstilo.formatadorInteiro()
        }

        GraficoEstilo.aplicarBarras(chartDistribuicao, detalhe.distribuicao.map { it.rotulo })
        chartDistribuicao.axisLeft.granularity = 1f
        GraficoEstilo.aoTocar(chartDistribuicao) { indice, valor ->
            detalhe.distribuicao.getOrNull(indice)?.let {
                findViewById<TextView>(R.id.txtLegendaDistribuicao).text =
                    "Faixa ${it.rotulo}: ${valor.toInt()} nota${if (valor.toInt() == 1) "" else "s"} " +
                    "(${Formato.percentual(it.percentual)} do total)"
            }
        }

        chartDistribuicao.data = BarData(dataSet).apply { barWidth = 0.6f }
        chartDistribuicao.invalidate()
        chartDistribuicao.animateY(700)
    }

    private fun montarGraficoAvaliacoes(detalhe: TurmaDetalhe) {
        if (detalhe.porAvaliacao.isEmpty()) {
            chartAvaliacoes.clear()
            chartAvaliacoes.invalidate()
            return
        }

        val entradas = detalhe.porAvaliacao.mapIndexed { i, av ->
            BarEntry(i.toFloat(), av.media.toFloat())
        }

        // Rótulo curto ("Prova 1"), senão o título inteiro não cabe no celular.
        val rotulos = detalhe.porAvaliacao.map { it.titulo.substringBefore(" ·").take(14) }

        val dataSet = BarDataSet(entradas, "Média da avaliação").apply {
            color = GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.Serie2)
            valueTextColor = GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.TextoPrincipal)
            valueTextSize = 11f
            valueFormatter = GraficoEstilo.formatadorNota()
        }

        GraficoEstilo.aplicarBarras(
            chart = chartAvaliacoes,
            rotulos = rotulos,
            rotacionarRotulos = true,
            maximoEixoY = 10f
        )
        GraficoEstilo.aoTocar(chartAvaliacoes) { indice, _ ->
            detalhe.porAvaliacao.getOrNull(indice)?.let {
                findViewById<TextView>(R.id.txtLegendaAvaliacoes).text =
                    "${it.titulo}: média ${Formato.nota(it.media)} · " +
                    "maior ${Formato.nota(it.maiorNota)} · menor ${Formato.nota(it.menorNota)}"
            }
        }

        chartAvaliacoes.data = BarData(dataSet).apply { barWidth = 0.55f }
        chartAvaliacoes.invalidate()
        chartAvaliacoes.animateY(700)
    }

    private fun montarGraficoEvolucao(detalhe: TurmaDetalhe) {
        if (detalhe.evolucao.isEmpty()) {
            chartEvolucao.clear()
            chartEvolucao.invalidate()
            return
        }

        val entradas = detalhe.evolucao.mapIndexed { i, ponto ->
            Entry(i.toFloat(), ponto.media.toFloat())
        }

        val corLinha = GraficoEstilo.cor(this, R.color.Destaque)
        val dataSet = LineDataSet(entradas, detalhe.nomeTurma).apply {
            color = corLinha
            setCircleColor(corLinha)
            lineWidth = 2.4f
            circleRadius = 4.5f
            setDrawCircleHole(false)
            setDrawValues(true)
            valueTextColor = GraficoEstilo.cor(this@TurmaAnaliseActivity, R.color.TextoSecundario)
            valueTextSize = 10f
            valueFormatter = GraficoEstilo.formatadorNota()
            mode = LineDataSet.Mode.CUBIC_BEZIER
            setDrawFilled(true)
            fillColor = corLinha
            fillAlpha = 40
        }

        GraficoEstilo.aplicarLinhas(chartEvolucao, detalhe.evolucao.map { it.rotulo }, comLegenda = false)
        chartEvolucao.data = LineData(dataSet)
        chartEvolucao.invalidate()
        chartEvolucao.animateY(700)
    }

    // ── Lista de alunos e edição ────────────────────────────────────────────

    private fun montarListaAlunos(detalhe: TurmaDetalhe) {
        val temAlunos = detalhe.alunos.isNotEmpty()
        rvAlunos.visibility = if (temAlunos) View.VISIBLE else View.GONE
        txtVazioAlunos.visibility = if (temAlunos) View.GONE else View.VISIBLE

        if (!temAlunos) return

        if (adapter == null) {
            adapter = AlunoAnaliseAdapter(detalhe.alunos) { abrirEdicao(it) }
            rvAlunos.adapter = adapter
        } else {
            adapter?.atualizar(detalhe.alunos)
        }
    }

    private fun abrirEdicao(aluno: AlunoAnalise) {
        val idAvaliacaoAtual = filtro.idAvaliacao.toIntOrNull()

        EditarNotaDialog.mostrar(this, aluno, idAvaliacaoAtual) { idNota, valor, aoTerminar ->
            lifecycleScope.launch {
                val resultado = withContext(Dispatchers.IO) {
                    repositorio.editarNota(idNota, valor)
                }

                when (resultado) {
                    is ResultadoApi.Sucesso -> {
                        aoTerminar(null)
                        Toast.makeText(this@TurmaAnaliseActivity, resultado.dados, Toast.LENGTH_LONG).show()
                        // Recarrega tudo: indicadores e os quatro gráficos passam
                        // a refletir a nota nova.
                        carregar()
                    }
                    is ResultadoApi.Erro -> aoTerminar(resultado.mensagem)
                    is ResultadoApi.SessaoExpirada -> {
                        aoTerminar("Sessão expirada.")
                        Sessao.encerrar(this@TurmaAnaliseActivity)
                        finish()
                    }
                }
            }
        }
    }
}
