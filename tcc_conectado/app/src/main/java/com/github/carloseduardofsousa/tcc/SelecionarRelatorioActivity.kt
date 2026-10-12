package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.os.Bundle
import android.view.View
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.Spinner
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

data class TurmaMenu(val id:Int,val nome:String,val serie:String)

class SelecionarRelatorioActivity : AppCompatActivity() {
    private lateinit var serie:Spinner
    private lateinit var turma:Spinner
    private lateinit var turmaContainer:LinearLayout
    private lateinit var status:TextView
    private lateinit var continuar:Button
    private var turmas=emptyList<TurmaMenu>()
    private var series=emptyList<String>()
    private val modo by lazy { intent.getStringExtra("MODO").orEmpty() }

    override fun onCreate(savedInstanceState:Bundle?) {
        super.onCreate(savedInstanceState)
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            val p=(24*resources.displayMetrics.density).toInt();setPadding(p,p,p,p)
            setBackgroundColor(UiPaleta.hex("#F5F7FA"))
        }
        root.addView(TextView(this).apply { text="‹  Voltar"; textSize=16f; setTextColor(getColor(R.color.Destaque)); minHeight=EstudoUi.dp(this@SelecionarRelatorioActivity,48); gravity=android.view.Gravity.CENTER_VERTICAL; setOnClickListener { finish() } })
        root.addView(TextView(this).apply {
            text=if(modo=="RANKING_SERIE") "Ranking por série" else "Desempenho da turma"
            textSize=26f;typeface=Typeface.DEFAULT_BOLD;setTextColor(UiPaleta.hex("#1A237E"));setPadding(0,24,0,4)
        })
        status=TextView(this).apply { text="Carregando opções…";textSize=16f;setPadding(0,12,0,16) };root.addView(status)
        val serieContainer=campo("Série").also { root.addView(it.first) };serie=serieContainer.second
        val turmaCampo=campo("Turma");turmaContainer=turmaCampo.first;turma=turmaCampo.second;root.addView(turmaContainer)
        continuar=Button(this).apply { text="Continuar";isEnabled=false;setOnClickListener { abrir() } };root.addView(continuar)
        setContentView(ScrollView(this).apply { addView(root) })
        turmaContainer.visibility=if(modo=="RANKING_SERIE") View.GONE else View.VISIBLE
        serie.onItemSelectedListener=object:AdapterView.OnItemSelectedListener {
            override fun onNothingSelected(parent:AdapterView<*>?) {}
            override fun onItemSelected(parent:AdapterView<*>?,view:View?,position:Int,id:Long) { atualizarTurmas() }
        }
        carregar()
    }

    private fun campo(titulo:String):Pair<LinearLayout,Spinner> {
        val spinner=Spinner(this)
        val container=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            addView(TextView(this@SelecionarRelatorioActivity).apply { text=titulo;textSize=15f;setPadding(0,12,0,4) })
            addView(spinner,LinearLayout.LayoutParams(-1,dp(60)))
        }
        return container to spinner
    }

    private fun carregar(): kotlinx.coroutines.Job = lifecycleScope.launch {
        val resposta=withContext(Dispatchers.IO) {
            ApiClient.postComStatus("opcoes_menu.php",mapOf("token" to Sessao.token(this@SelecionarRelatorioActivity)))
        }
        if(resposta.sessaoExpirada){Sessao.encerrar(this@SelecionarRelatorioActivity);return@launch}
        val json=runCatching{JSONObject(resposta.corpo.orEmpty())}.getOrNull()
        if(!resposta.sucesso||json?.optString("status")!="sucesso"){
            status.text=resposta.mensagemErro("Não foi possível carregar as opções.")
            continuar.text="Tentar novamente"; continuar.isEnabled=true
            continuar.setOnClickListener { continuar.isEnabled=false; carregar() }
            return@launch
        }
        continuar.text="Continuar"; continuar.setOnClickListener { abrir() }
        val listaTurmas=json.getJSONArray("turmas")
        turmas=(0 until listaTurmas.length()).map {
            val item=listaTurmas.getJSONObject(it)
            TurmaMenu(item.getInt("id_turma"),item.getString("nome_turma"),item.optString("serie"))
        }
        val listaSeries=json.getJSONArray("series")
        series=(0 until listaSeries.length()).map{listaSeries.getString(it)}.filter { it.isNotBlank() }
        serie.adapter=ArrayAdapter(this@SelecionarRelatorioActivity,android.R.layout.simple_spinner_dropdown_item,series)
        atualizarTurmas()
        status.text=when {
            series.isEmpty() -> "Nenhuma série vinculada ao seu perfil."
            modo!="RANKING_SERIE" && turmas.isEmpty() -> "Nenhuma turma vinculada ao seu perfil."
            modo=="RANKING_SERIE" -> "Selecione a série que deseja consultar."
            else -> "Selecione a série e a turma que deseja analisar."
        }
        continuar.isEnabled=series.isNotEmpty()&&(modo=="RANKING_SERIE"||(turma.tag as? List<*>)?.isNotEmpty()==true)
    }

    private fun atualizarTurmas(){
        if(series.isEmpty()){turma.adapter=null;turma.tag=emptyList<TurmaMenu>();return}
        val filtradas=turmas.filter{it.serie==series.getOrNull(serie.selectedItemPosition)}
        turma.adapter=ArrayAdapter(this,android.R.layout.simple_spinner_dropdown_item,filtradas.map{it.nome})
        turma.tag=filtradas
        if(::continuar.isInitialized) continuar.isEnabled=modo=="RANKING_SERIE"||filtradas.isNotEmpty()
    }

    private fun dp(valor:Int)=(valor*resources.displayMetrics.density).toInt()

    @Suppress("UNCHECKED_CAST")
    private fun abrir(){
        val serieSelecionada=series.getOrNull(serie.selectedItemPosition)?:return
        if(modo=="RANKING_SERIE") {
            startActivity(Intent(this,RankingActivity::class.java)
                .putExtra("SERIE",serieSelecionada)
                .putExtra("MES_REF",SimpleDateFormat("yyyy-MM",Locale.getDefault()).format(Date())))
            return
        }
        val lista=turma.tag as? List<TurmaMenu>?:return
        val selecionada=lista.getOrNull(turma.selectedItemPosition)?:return
        startActivity(Intent(this,DesempenhoTopicosActivity::class.java)
            .putExtra("ID_TURMA",selecionada.id)
            .putExtra("NOME_TURMA",selecionada.nome))
    }
}
