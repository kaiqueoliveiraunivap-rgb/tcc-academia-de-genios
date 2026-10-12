package com.github.carloseduardofsousa.tcc

import android.content.res.ColorStateList
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.Space
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.Calendar

data class ItemRanking(val nome:String,val pontos:Int,val proprio:Boolean)

class RankingActivity : AppCompatActivity() {
    private lateinit var titulo:TextView
    private lateinit var status:TextView
    private lateinit var progresso:ProgressBar
    private lateinit var lista:LinearLayout
    private lateinit var anterior:Button
    private lateinit var seguinte:Button
    private lateinit var periodo:TextView
    private val mes = Calendar.getInstance()
    private var ocupado = false

    override fun onCreate(savedInstanceState:Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); return }
        val referencia = savedInstanceState?.getString("mes") ?: intent.getStringExtra("MES_REF")
        if (referencia?.matches(Regex("\\d{4}-(0[1-9]|1[0-2])")) == true) {
            mes.set(Calendar.DAY_OF_MONTH, 1)
            mes.set(Calendar.YEAR, referencia.substring(0,4).toInt())
            mes.set(Calendar.MONTH, referencia.substring(5,7).toInt()-1)
        }
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            setPadding(dp(20),dp(20),dp(20),dp(12))
            setBackgroundColor(UiPaleta.hex("#F5F7FA"))
        }
        root.addView(Button(this).apply {
            text="← Voltar";isAllCaps=false
            backgroundTintList=ColorStateList.valueOf(UiPaleta.superficie())
            setTextColor(UiPaleta.hex("#1A237E"));setOnClickListener { finish() }
        })
        titulo=TextView(this).apply {
            text="Ranking";textSize=26f;typeface=Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E"));setPadding(0,dp(18),0,dp(5))
        };root.addView(titulo)
        val controle = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        periodo = EstudoUi.texto(this, "", 17f, true); controle.addView(periodo)
        val botoes = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL }
        anterior = EstudoUi.botao(this, "← Anterior") { mudarMes(-1) }
        seguinte = EstudoUi.botao(this, "Próximo →") { mudarMes(1) }
        botoes.addView(anterior, LinearLayout.LayoutParams(0,-2,1f))
        botoes.addView(seguinte, LinearLayout.LayoutParams(0,-2,1f))
        controle.addView(botoes); root.addView(controle)
        status=TextView(this).apply {
            text="Carregando pontuações…";textSize=15f;setTextColor(UiPaleta.hex("#666680"));setPadding(0,0,0,dp(14))
        };root.addView(status)
        progresso=ProgressBar(this);root.addView(progresso)
        lista=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { addView(lista) },LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root)
        setContentView(root)
        carregar()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putString("mes", SimpleDateFormat("yyyy-MM", Locale.US).format(mes.time))
    }

    private fun mudarMes(delta: Int) {
        if (ocupado) return
        mes.add(Calendar.MONTH, delta); carregar()
    }

    private fun atualizarPeriodo() {
        periodo.text = "Ranking mensal · " + SimpleDateFormat("MMMM 'de' yyyy", Locale.forLanguageTag("pt-BR")).format(mes.time)
        anterior.isEnabled = !ocupado && mes.get(Calendar.YEAR) > 2000
        val atual = Calendar.getInstance()
        seguinte.isEnabled = !ocupado && (mes.get(Calendar.YEAR) * 12 + mes.get(Calendar.MONTH) < atual.get(Calendar.YEAR) * 12 + atual.get(Calendar.MONTH))
    }

    private fun carregar() {
        if (ocupado) return
        ocupado = true; atualizarPeriodo()
        lifecycleScope.launch {
        progresso.visibility=View.VISIBLE;lista.removeAllViews()
        val mes=SimpleDateFormat("yyyy-MM",Locale.US).format(this@RankingActivity.mes.time)
        val parametros=mutableMapOf(
            "token" to Sessao.token(this@RankingActivity),
            "mes_referencia" to mes
        )
        intent.getStringExtra("SERIE")?.takeIf { it.isNotBlank() }?.let { parametros["serie"]=it }
        intent.getIntExtra("ID_TURMA",0).takeIf { it>0 }?.let { parametros["id_turma"]=it.toString() }
        val resposta=withContext(Dispatchers.IO) { ApiClient.postComStatus("ranking_serie.php",parametros) }
        ocupado=false; atualizarPeriodo()
        progresso.visibility=View.GONE
        if (resposta.sessaoExpirada) { Sessao.encerrar(this@RankingActivity);return@launch }
        val json=runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
        if (!resposta.sucesso || json?.optString("status")!="sucesso") {
            status.text=resposta.mensagemErro("Não foi possível carregar o ranking.")
            lista.addView(Button(this@RankingActivity).apply { text="Tentar novamente";isAllCaps=false;setOnClickListener { carregar() } })
            return@launch
        }
        val serie=json.getString("serie")
        titulo.text="Ranking — $serie"
        val array=json.getJSONArray("ranking")
        val itens=(0 until array.length()).map { indice ->
            val item=array.getJSONObject(indice)
            ItemRanking(item.getString("nome"),item.getInt("pontos"),item.optBoolean("proprio"))
        }
        if (itens.isEmpty()) {
            status.text="Nenhum aluno pontuou nesta série no mês selecionado."
            return@launch
        }
        val totalPontos=itens.sumOf { it.pontos }
        status.text="Pontos acumulados no mês selecionado · $totalPontos pontos distribuídos"
        val maximo=maxOf(1,itens.maxOf { it.pontos })
            itens.forEachIndexed { indice,item -> adicionarBarra(indice+1,item,maximo) }
        }
    }

    private fun adicionarBarra(posicao:Int,item:ItemRanking,maximo:Int) {
        val cor=when(posicao) {
            1->UiPaleta.hex("#D4AF37")
            2->UiPaleta.hex("#90A4AE")
            3->UiPaleta.hex("#BF7B45")
            else->UiPaleta.hex("#5C6BC0")
        }
        val card=MaterialCardView(this).apply {
            radius=dp(15).toFloat();cardElevation=0f;strokeWidth=dp(if(item.proprio) 2 else 1)
            strokeColor=UiPaleta.hex(if(item.proprio) "#5C6BC0" else "#E1E3EE")
            setCardBackgroundColor(UiPaleta.superficie())
            layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(11) }
        }
        val bloco=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL;setPadding(dp(14),dp(13),dp(14),dp(14)) }
        val cabecalho=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL }
        cabecalho.addView(TextView(this).apply {
            text=when(posicao){1->"🥇";2->"🥈";3->"🥉";else->"${posicao}º"}
            textSize=18f;gravity=Gravity.CENTER
        },LinearLayout.LayoutParams(dp(42),-2))
        cabecalho.addView(TextView(this).apply {
            text=item.nome+(if(item.proprio) " (você)" else "")
            textSize=16f;typeface=Typeface.DEFAULT_BOLD;setTextColor(UiPaleta.hex("#1A1A2E"))
        },LinearLayout.LayoutParams(0,-2,1f))
        cabecalho.addView(TextView(this).apply {
            text="${item.pontos} pts";textSize=16f;typeface=Typeface.DEFAULT_BOLD;setTextColor(cor)
        })
        bloco.addView(cabecalho)

        val trilho=LinearLayout(this).apply {
            orientation=LinearLayout.HORIZONTAL;weightSum=maximo.toFloat()
            background=GradientDrawable().apply { setColor(UiPaleta.hex("#E7E9F2"));cornerRadius=dp(8).toFloat() }
        }
        if (item.pontos>0) trilho.addView(View(this).apply {
            background=GradientDrawable().apply { setColor(cor);cornerRadius=dp(8).toFloat() }
        },LinearLayout.LayoutParams(0,-1,item.pontos.toFloat()))
        if (item.pontos<maximo) trilho.addView(Space(this),LinearLayout.LayoutParams(0,-1,(maximo-item.pontos).toFloat()))
        bloco.addView(trilho,LinearLayout.LayoutParams(-1,dp(20)).apply { topMargin=dp(10) })
        card.addView(bloco);lista.addView(card)
    }

    private fun dp(valor:Int)=(valor*resources.displayMetrics.density).toInt()
}
