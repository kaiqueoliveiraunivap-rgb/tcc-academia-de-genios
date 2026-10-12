package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.content.res.ColorStateList
import android.graphics.Color
import android.graphics.Typeface
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.github.mikephil.charting.charts.PieChart
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.PieData
import com.github.mikephil.charting.data.PieDataSet
import com.github.mikephil.charting.data.PieEntry
import com.github.mikephil.charting.highlight.Highlight
import com.github.mikephil.charting.listener.OnChartValueSelectedListener
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.text.Normalizer
import java.util.Locale

data class ItemDesempenho(
    val nome: String,
    val materia: String,
    val respostas: Int,
    val acertos: Int,
    val erros: Int,
    val percentualAcertos: Double,
    val quizzes: Int,
    val alunos: Int
)

class DesempenhoTopicosActivity : AppCompatActivity() {
    private lateinit var status: TextView
    private lateinit var conteudo: LinearLayout
    private lateinit var progresso: ProgressBar
    private val materia by lazy { intent.getStringExtra("MATERIA").orEmpty() }
    private val detalhado get() = materia.isNotBlank()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); return }

        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            setPadding(dp(20),dp(20),dp(20),dp(12))
            setBackgroundColor(UiPaleta.hex("#F5F7FA"))
        }
        root.addView(Button(this).apply {
            text="← Voltar"; isAllCaps=false
            backgroundTintList=ColorStateList.valueOf(UiPaleta.superficie())
            setTextColor(UiPaleta.hex("#1A237E"))
            setOnClickListener { finish() }
        })
        root.addView(TextView(this).apply {
            text=if (detalhado) materia else if (Sessao.ehProfessor(this@DesempenhoTopicosActivity)) "Desempenho da turma" else "Meu desempenho"
            textSize=27f; typeface=Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,dp(18),0,dp(4))
        })
        root.addView(TextView(this).apply {
            text=if (Sessao.ehProfessor(this@DesempenhoTopicosActivity))
                intent.getStringExtra("NOME_TURMA").orEmpty()
            else Sessao.nome(this@DesempenhoTopicosActivity)
            textSize=16f; setTextColor(UiPaleta.hex("#666680"))
        })
        progresso=ProgressBar(this).also { root.addView(it) }
        status=TextView(this).apply {
            text="Carregando os resultados dos quizzes…"; textSize=15f
            setTextColor(UiPaleta.hex("#455A64")); setPadding(0,dp(12),0,dp(12))
        }
        root.addView(status)
        conteudo=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { addView(conteudo) },LinearLayout.LayoutParams(-1,0,1f))
        setContentView(root)
        carregar()
    }

    private fun carregar()=lifecycleScope.launch {
        val parametros=mutableMapOf(
            "token" to Sessao.token(this@DesempenhoTopicosActivity),
            "nivel" to if (detalhado) "conteudos" else "materias"
        )
        if (detalhado) parametros["materia"]=materia
        if (Sessao.ehProfessor(this@DesempenhoTopicosActivity))
            parametros["id_turma"]=intent.getIntExtra("ID_TURMA",0).toString()

        val resposta=withContext(Dispatchers.IO) {
            ApiClient.postComStatus("desempenho_estudos.php",parametros)
        }
        progresso.visibility=View.GONE
        if (resposta.sessaoExpirada) { Sessao.encerrar(this@DesempenhoTopicosActivity); return@launch }
        val json=runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
        if (!resposta.sucesso || json?.optString("status")!="sucesso") {
            status.text=json?.optString("mensagem") ?: "Não foi possível carregar o desempenho."
            adicionarTentarNovamente()
            return@launch
        }
        val array=json.getJSONArray("itens")
        val itens=(0 until array.length()).map { indice ->
            val item=array.getJSONObject(indice)
            ItemDesempenho(
                item.getString("nome"),item.getString("materia"),item.getInt("respostas"),
                item.getInt("acertos"),item.getInt("erros"),item.getDouble("percentual_acertos"),
                item.getInt("quizzes"),item.getInt("alunos")
            )
        }.sortedWith(compareByDescending<ItemDesempenho> { it.erros }.thenBy { it.nome })
        if (itens.isEmpty()) {
            status.text=if (Sessao.ehProfessor(this@DesempenhoTopicosActivity))
                "Esta turma ainda não concluiu quizzes${if (detalhado) " de $materia" else ""}."
            else "Você ainda não concluiu quizzes${if (detalhado) " de $materia" else ""}."
            return@launch
        }
        montar(json.getJSONObject("resumo"),itens)
    }

    private fun montar(resumo: JSONObject,itens: List<ItemDesempenho>) {
        conteudo.removeAllViews()
        val percentual=resumo.getDouble("percentual_acertos")
        status.text=when {
            resumo.getInt("erros")==0 -> "Nenhum erro registrado. Toque em uma fatia para ver os números."
            detalhado -> "Cada cor representa um conteúdo e o tamanho compara o aproveitamento. Toque para ver os números."
            else -> "Cada matéria possui uma cor e o tamanho compara o aproveitamento. Toque para ver seus conteúdos."
        }

        // Mantém todas as matérias no gráfico, inclusive as que tiveram zero
        // erro ou zero acerto. O valor mínimo deixa a fatia selecionável sem
        // alterar o percentual real mostrado no centro e nos cards.
        val entradas=itens.map { item ->
            PieEntry(maxOf(item.percentualAcertos.toFloat(),1f),item.nome,item)
        }
        val cores=entradas.mapIndexed { indice, entrada ->
            val item=entrada.data as ItemDesempenho
            if (detalhado) COR_CONTEUDOS[indice % COR_CONTEUDOS.size] else corMateria(item.materia)
        }
        val chart=PieChart(this).apply {
            layoutParams=LinearLayout.LayoutParams(-1,dp(365))
            description.isEnabled=false; legend.isEnabled=false
            setUsePercentValues(false); setDrawEntryLabels(false)
            isRotationEnabled=true; isHighlightPerTapEnabled=true
            holeRadius=57f; transparentCircleRadius=62f
            setHoleColor(UiPaleta.superficie()); setCenterTextColor(UiPaleta.hex("#1A237E"))
            setCenterTextSize(19f); centerText=String.format(Locale.forLanguageTag("pt-BR"),"%.1f%%\nde acertos",percentual)
            setExtraOffsets(8f,8f,8f,8f)
        }
        val conjunto=PieDataSet(entradas,"").apply {
            colors=cores; sliceSpace=3f; selectionShift=8f
            setDrawValues(false)
        }
        chart.data=PieData(conjunto)
        chart.setOnChartValueSelectedListener(object:OnChartValueSelectedListener {
            override fun onNothingSelected() {}
            override fun onValueSelected(e:Entry?,h:Highlight?) {
                val item=(e as? PieEntry)?.data as? ItemDesempenho ?: return
                mostrarDetalhe(item)
                if (!detalhado) abrirConteudos(item)
            }
        })
        chart.invalidate(); chart.animateY(650)
        conteudo.addView(chart)

        conteudo.addView(TextView(this).apply {
            text=if (detalhado) "Conteúdos respondidos" else "Matérias respondidas"
            textSize=20f; typeface=Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,dp(12),0,dp(10))
        })
        itens.forEachIndexed { indice,item -> adicionarItem(item,if (detalhado) COR_CONTEUDOS[indice%COR_CONTEUDOS.size] else corMateria(item.materia)) }
    }

    private fun adicionarItem(item:ItemDesempenho,cor:Int) {
        val card=MaterialCardView(this).apply {
            radius=dp(15).toFloat(); strokeWidth=dp(1); strokeColor=UiPaleta.hex("#E1E3EE")
            setCardBackgroundColor(UiPaleta.superficie()); cardElevation=0f
            layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(10) }
            isClickable=true; isFocusable=true
            setOnClickListener { mostrarDetalhe(item); if (!detalhado) abrirConteudos(item) }
        }
        val linha=LinearLayout(this).apply {
            orientation=LinearLayout.HORIZONTAL; gravity=Gravity.CENTER_VERTICAL
            setPadding(dp(14),dp(14),dp(14),dp(14))
        }
        linha.addView(View(this).apply { backgroundTintList=ColorStateList.valueOf(cor); setBackgroundColor(cor) },LinearLayout.LayoutParams(dp(12),dp(48)).apply { marginEnd=dp(13) })
        linha.addView(LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            addView(TextView(this@DesempenhoTopicosActivity).apply {
                text=item.nome; textSize=16f; typeface=Typeface.DEFAULT_BOLD; setTextColor(UiPaleta.hex("#1A1A2E"))
            })
            addView(TextView(this@DesempenhoTopicosActivity).apply {
                text="${formatar(item.percentualAcertos)}% de acertos · ${item.acertos} certas · ${item.erros} erradas"
                textSize=14f; setTextColor(UiPaleta.hex("#666680")); setPadding(0,dp(4),0,0)
            })
        },LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f))
        if (!detalhado) linha.addView(TextView(this).apply { text="›";textSize=29f;setTextColor(UiPaleta.hex("#9FA8DA")) })
        card.addView(linha); conteudo.addView(card)
    }

    private fun mostrarDetalhe(item:ItemDesempenho) {
        val complemento=if (Sessao.ehProfessor(this)) " · ${item.alunos} aluno${if(item.alunos==1)"" else "s"}" else ""
        status.text="${item.nome}: ${formatar(item.percentualAcertos)}% de acertos em ${item.respostas} respostas$complemento."
    }

    private fun abrirConteudos(item:ItemDesempenho) {
        startActivity(Intent(this,DesempenhoTopicosActivity::class.java).apply {
            putExtra("MATERIA",item.materia)
            putExtra("ID_TURMA",intent.getIntExtra("ID_TURMA",0))
            putExtra("NOME_TURMA",intent.getStringExtra("NOME_TURMA"))
        })
    }

    private fun adicionarTentarNovamente() {
        conteudo.removeAllViews()
        conteudo.addView(Button(this).apply { text="Tentar novamente";isAllCaps=false;setOnClickListener { carregar() } })
    }

    private fun corMateria(nome:String):Int {
        val chave=Normalizer.normalize(nome,Normalizer.Form.NFD).replace("\\p{M}+".toRegex(),"").lowercase()
        return CORES_MATERIAS.entries.firstOrNull { chave.contains(it.key) }?.value
            ?: COR_CONTEUDOS[kotlin.math.abs(chave.hashCode())%COR_CONTEUDOS.size]
    }
    private fun formatar(valor:Double)=String.format(Locale.forLanguageTag("pt-BR"),"%.1f",valor)
    private fun dp(valor:Int)=(valor*resources.displayMetrics.density).toInt()

    companion object {
        private val CORES_MATERIAS=linkedMapOf(
            "matematica" to Color.rgb(63,81,181), "portugues" to Color.rgb(229,57,53),
            "fisica" to Color.rgb(0,137,123), "quimica" to Color.rgb(142,36,170),
            "biologia" to Color.rgb(67,160,71), "historia" to Color.rgb(251,140,0),
            "geografia" to Color.rgb(109,76,65), "filosofia" to Color.rgb(84,110,122),
            "sociologia" to Color.rgb(216,27,96), "redacao" to Color.rgb(0,151,167)
        )
        private val COR_CONTEUDOS=listOf(
            Color.rgb(92,107,192),Color.rgb(38,166,154),Color.rgb(239,108,87),
            Color.rgb(126,87,194),Color.rgb(212,175,55),Color.rgb(66,165,245),
            Color.rgb(236,64,122),Color.rgb(102,187,106),Color.rgb(255,167,38)
        )
    }
}
