package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Typeface
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class ProfessorConteudosActivity : AppCompatActivity() {
    private lateinit var lista:LinearLayout
    private lateinit var status:TextView
    private lateinit var progresso:ProgressBar
    private val idTurma get()=intent.getIntExtra("ID_TURMA",0)
    private fun dp(n:Int)=(n*resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.ehProfessor(this) || idTurma<=0) { finish(); return }
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL; setPadding(dp(22),dp(20),dp(22),dp(18))
            setBackgroundColor(getColor(R.color.BackGround))
        }
        root.addView(TextView(this).apply {
            text="‹  Minhas turmas"; textSize=16f; minHeight=dp(48); gravity=Gravity.CENTER_VERTICAL
            setTextColor(getColor(R.color.Destaque)); setOnClickListener { finish() }
        })
        root.addView(TextView(this).apply {
            text=intent.getStringExtra("NOME_TURMA").orEmpty(); textSize=27f
            typeface=Typeface.DEFAULT_BOLD; setTextColor(getColor(R.color.TextoPrincipal))
            setPadding(0,dp(12),0,dp(3))
        })
        root.addView(TextView(this).apply {
            text="Selecione o conteúdo para orientar, publicar uma atividade ou ver resultados."
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
        })
        progresso=ProgressBar(this).apply { visibility=View.GONE }
        root.addView(progresso,LinearLayout.LayoutParams(-2,-2).apply { topMargin=dp(18) })
        status=TextView(this).apply {
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
            setPadding(0,dp(15),0,dp(12))
        }
        root.addView(status)
        lista=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { isFillViewport=true; addView(lista) },LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root); setContentView(root)
    }

    override fun onResume() { super.onResume(); if (::lista.isInitialized) carregar() }

    private fun carregar() {
        if (!::lista.isInitialized) return
        progresso.visibility=View.VISIBLE; status.text="Carregando conteúdos da turma…"; lista.removeAllViews()
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("professor_pedagogico.php",mapOf(
                    "token" to Sessao.token(this@ProfessorConteudosActivity),"acao" to "conteudos",
                    "id_turma" to idTurma.toString()))
            }
            progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ProfessorConteudosActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                status.text=r.mensagemErro("Não foi possível carregar os conteúdos.")
                lista.addView(EstudoUi.botao(this@ProfessorConteudosActivity,"Tentar novamente") { carregar() })
                return@launch
            }
            val turma=json.getJSONObject("turma")
            val itens=json.getJSONArray("conteudos")
            status.text="${turma.optString("serie")} · ${turma.optString("ano_letivo")} · ${itens.length()} conteúdos"
            if (itens.length()==0) {
                lista.addView(TextView(this@ProfessorConteudosActivity).apply {
                    text="A secretaria ainda não classificou conteúdos revisados para esta série."
                    textSize=16f; setTextColor(getColor(R.color.TextoSecundario))
                })
                return@launch
            }
            var grupo=""
            for (i in 0 until itens.length()) {
                val item=itens.getJSONObject(i)
                val bimestre=item.optInt("bimestre",0)
                val atual=item.optString("materia")+" · "+(if(bimestre in 1..4) "${bimestre}º bimestre" else "Sem bimestre")
                if (atual!=grupo) {
                    grupo=atual
                    lista.addView(TextView(this@ProfessorConteudosActivity).apply {
                        text=atual.uppercase(); textSize=12f; typeface=Typeface.DEFAULT_BOLD
                        letterSpacing=.08f; setTextColor(getColor(R.color.Destaque))
                        setPadding(0,dp(16),0,dp(9))
                    })
                }
                lista.addView(MaterialCardView(this@ProfessorConteudosActivity).apply {
                    radius=dp(16).toFloat(); cardElevation=0f
                    strokeWidth=dp(1); strokeColor=getColor(R.color.BordaButton)
                    setCardBackgroundColor(UiPaleta.superficie())
                    isClickable=true; isFocusable=true
                    layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(10) }
                    addView(LinearLayout(this@ProfessorConteudosActivity).apply {
                        orientation=LinearLayout.VERTICAL; setPadding(dp(18),dp(16),dp(18),dp(16))
                        addView(TextView(this@ProfessorConteudosActivity).apply {
                            text=item.optString("nome"); textSize=17f; typeface=Typeface.DEFAULT_BOLD
                            setTextColor(getColor(R.color.TextoPrincipal))
                        })
                        addView(TextView(this@ProfessorConteudosActivity).apply {
                            val n=item.optInt("atividades")
                            text=if(n>0) "$n atividade(s) publicada(s)" else "Preparar estudo ou publicar atividade"
                            textSize=14f; setTextColor(getColor(R.color.TextoSecundario))
                            setPadding(0,dp(6),0,0)
                        })
                    })
                    setOnClickListener {
                        startActivity(Intent(this@ProfessorConteudosActivity,ProfessorConteudoDetalheActivity::class.java).apply {
                            putExtra("ID_TURMA",idTurma)
                            putExtra("ID_CONTEUDO",item.getInt("id_conteudo"))
                            putExtra("NOME_CONTEUDO",item.getString("nome"))
                            putExtra("MATERIA",item.getString("materia"))
                        })
                    }
                })
            }
        }
    }
}
