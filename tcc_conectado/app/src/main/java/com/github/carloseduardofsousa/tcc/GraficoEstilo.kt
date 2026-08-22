package com.github.carloseduardofsousa.tcc

import android.content.Context
import androidx.core.content.ContextCompat
import com.github.mikephil.charting.charts.BarChart
import com.github.mikephil.charting.charts.Chart
import com.github.mikephil.charting.charts.LineChart
import com.github.mikephil.charting.charts.PieChart
import com.github.mikephil.charting.components.Legend
import com.github.mikephil.charting.components.XAxis
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.formatter.IndexAxisValueFormatter
import com.github.mikephil.charting.formatter.ValueFormatter
import com.github.mikephil.charting.highlight.Highlight
import com.github.mikephil.charting.listener.OnChartValueSelectedListener

/**
 * Estilo único dos gráficos do sistema.
 *
 * O visual (grade #EEEEF2, textos #666680, sem descrição, animação de 700ms)
 * é o mesmo que TurmaDesempenhoActivity já usava; foi extraído para cá para
 * que todos os gráficos novos fiquem idênticos aos antigos e para não repetir
 * vinte linhas de configuração em cada tela.
 */
object GraficoEstilo {

    /** Paleta das séries, na ordem em que devem ser usadas. */
    fun cores(context: Context): List<Int> = listOf(
        R.color.Serie1, R.color.Serie2, R.color.Serie3, R.color.Serie4, R.color.Serie5
    ).map { ContextCompat.getColor(context, it) }

    fun cor(context: Context, recurso: Int): Int = ContextCompat.getColor(context, recurso)

    /** Configuração comum a qualquer gráfico. */
    private fun aplicarBase(chart: Chart<*>) {
        chart.description.isEnabled = false
        chart.setNoDataText("Sem dados para exibir.")
        chart.setNoDataTextColor(cor(chart.context, R.color.TextoSecundario))
        chart.legend.textColor = cor(chart.context, R.color.TextoSecundario)
        chart.legend.textSize = 11f
        chart.animateY(700)
    }

    /**
     * Gráfico de barras com rótulos de texto no eixo X.
     * [rotacionarRotulos] evita que nomes longos se sobreponham no celular.
     */
    fun aplicarBarras(
        chart: BarChart,
        rotulos: List<String>,
        comLegenda: Boolean = false,
        rotacionarRotulos: Boolean = false,
        maximoEixoY: Float? = null
    ) {
        aplicarBase(chart)

        chart.legend.isEnabled = comLegenda
        chart.legend.verticalAlignment = Legend.LegendVerticalAlignment.BOTTOM
        chart.legend.horizontalAlignment = Legend.LegendHorizontalAlignment.CENTER
        chart.legend.orientation = Legend.LegendOrientation.HORIZONTAL
        chart.legend.setDrawInside(false)

        chart.setFitBars(true)
        chart.setDrawGridBackground(false)
        chart.setDrawBorders(false)
        chart.setScaleEnabled(false)
        chart.setPinchZoom(false)
        chart.extraBottomOffset = if (comLegenda) 22f else 8f

        chart.xAxis.apply {
            position = XAxis.XAxisPosition.BOTTOM
            valueFormatter = IndexAxisValueFormatter(rotulos)
            granularity = 1f
            labelCount = rotulos.size
            setDrawGridLines(false)
            textColor = cor(chart.context, R.color.TextoSecundario)
            textSize = 10f
            labelRotationAngle = if (rotacionarRotulos && rotulos.size > 4) -30f else 0f
        }

        chart.axisLeft.apply {
            axisMinimum = 0f
            maximoEixoY?.let { axisMaximum = it }
            setDrawGridLines(true)
            gridColor = cor(chart.context, R.color.GradeGrafico)
            textColor = cor(chart.context, R.color.TextoSecundario)
            textSize = 10f
        }
        chart.axisRight.isEnabled = false
    }

    /** Gráfico de linhas (evolução ao longo do tempo). */
    fun aplicarLinhas(
        chart: LineChart,
        rotulos: List<String>,
        comLegenda: Boolean = true
    ) {
        aplicarBase(chart)

        chart.legend.isEnabled = comLegenda
        chart.legend.verticalAlignment = Legend.LegendVerticalAlignment.BOTTOM
        chart.legend.horizontalAlignment = Legend.LegendHorizontalAlignment.CENTER
        chart.legend.orientation = Legend.LegendOrientation.HORIZONTAL
        chart.legend.setDrawInside(false)
        chart.legend.isWordWrapEnabled = true
        chart.legend.yOffset = 6f

        chart.setDrawGridBackground(false)
        chart.setDrawBorders(false)
        chart.setScaleEnabled(false)
        chart.setPinchZoom(false)
        // Com legenda embaixo é preciso mais folga, senão ela encosta nos
        // rótulos do eixo X (mar/26, abr/26...).
        chart.extraBottomOffset = if (comLegenda) 22f else 8f

        chart.xAxis.apply {
            position = XAxis.XAxisPosition.BOTTOM
            valueFormatter = IndexAxisValueFormatter(rotulos)
            granularity = 1f
            setDrawGridLines(false)
            textColor = cor(chart.context, R.color.TextoSecundario)
            textSize = 10f
        }

        chart.axisLeft.apply {
            axisMinimum = 0f
            axisMaximum = 10f
            setDrawGridLines(true)
            gridColor = cor(chart.context, R.color.GradeGrafico)
            textColor = cor(chart.context, R.color.TextoSecundario)
            textSize = 10f
        }
        chart.axisRight.isEnabled = false
    }

    /** Rosca de aprovação x reprovação. */
    fun aplicarPizza(chart: PieChart) {
        aplicarBase(chart)

        chart.legend.isEnabled = true
        chart.legend.verticalAlignment = Legend.LegendVerticalAlignment.BOTTOM
        chart.legend.horizontalAlignment = Legend.LegendHorizontalAlignment.CENTER
        chart.legend.orientation = Legend.LegendOrientation.HORIZONTAL

        chart.setUsePercentValues(false)
        chart.setDrawEntryLabels(false)
        chart.isRotationEnabled = false
        chart.setHoleColor(cor(chart.context, R.color.CardFundo))
        chart.holeRadius = 58f
        chart.transparentCircleRadius = 62f
        chart.setCenterTextColor(cor(chart.context, R.color.btnSelecionado))
        chart.setCenterTextSize(15f)
    }

    /** Mostra "8,7" em vez de "8.7" nos rótulos das barras. */
    fun formatadorNota(casas: Int = 1) = object : ValueFormatter() {
        override fun getFormattedValue(value: Float): String =
            String.format(java.util.Locale.forLanguageTag("pt-BR"), "%.${casas}f", value)
    }

    /** Rótulo inteiro, para contagens (quantidade de alunos por faixa). */
    fun formatadorInteiro() = object : ValueFormatter() {
        override fun getFormattedValue(value: Float): String = value.toInt().toString()
    }

    /**
     * Tooltip: o MPAndroidChart destaca a barra tocada; aqui exibimos o texto
     * correspondente para quem toca no gráfico entender o valor exato.
     */
    fun aoTocar(chart: Chart<*>, aoSelecionar: (indice: Int, valor: Float) -> Unit) {
        chart.setOnChartValueSelectedListener(object : OnChartValueSelectedListener {
            override fun onValueSelected(e: Entry?, h: Highlight?) {
                if (e != null) aoSelecionar(e.x.toInt(), e.y)
            }
            override fun onNothingSelected() {}
        })
    }
}
