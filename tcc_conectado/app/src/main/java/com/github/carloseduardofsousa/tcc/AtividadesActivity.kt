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

class AtividadesActivity : AppCompatActivity() {
    private lateinit var lista:LinearLayout
    private lateinit var status:TextView
    private lateinit var progresso:ProgressBar
    private var ocupado=false
    private var versaoProgresso=ProgressoAluno.versao
    private fun dp(n:Int)=(n*resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this) || Sessao.ehProfessor(this)) { finish(); return }
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL; setPadding(dp(22),dp(20),dp(22),dp(18))
            setBackgroundColor(getColor(R.color.BackGround))
        }
        root.addView(TextView(this).apply {
            text="‹  Início"; textSize=16f; minHeight=dp(48); gravity=Gravity.CENTER_VERTICAL
            setTextColor(getColor(R.color.Destaque)); setOnClickListener { finish() }
        })
        root.addView(TextView(this).apply {
            text="Atividades"; textSize=27f; typeface=Typeface.DEFAULT_BOLD
            setTextColor(getColor(R.color.TextoPrincipal)); setPadding(0,dp(12),0,dp(4))
        })
        root.addView(TextView(this).apply {
            text="Estudos propostos para sua turma. Você também pode estudar livremente."
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
        })
        progresso=ProgressBar(this).apply { visibility=View.GONE }
        root.addView(progresso,LinearLayout.LayoutParams(-2,-2).apply { topMargin=dp(18) })
        status=TextView(this).apply {
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
            setPadding(0,dp(14),0,dp(12))
        }
        root.addView(status)
        lista=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { isFillViewport=true; addView(lista) },LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root); setContentView(root)
        carregar()
    }

    override fun onResume() {
        super.onResume()
        if (::lista.isInitialized && versaoProgresso!=ProgressoAluno.versao) {
            versaoProgresso=ProgressoAluno.versao; carregar()
        }
    }

    private fun carregar() {
        if (ocupado) return
        ocupado=true; progresso.visibility=View.VISIBLE; status.text="Carregando atividades…"
        lista.removeAllViews()
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("atividades_aluno.php",mapOf("token" to Sessao.token(this@AtividadesActivity)))
            }
            ocupado=false; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@AtividadesActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                status.text=r.mensagemErro("Não foi possível carregar as atividades.")
                lista.addView(EstudoUi.botao(this@AtividadesActivity,"Tentar novamente") { carregar() })
                return@launch
            }
            val itens=json.getJSONArray("atividades")
            status.text=if (itens.length()==0) "Nenhuma atividade publicada para sua turma. Explore os conteúdos em Estudar."
                else "${itens.length()} ${if (itens.length()==1) "atividade" else "atividades"} da sua turma"
            for (i in 0 until itens.length()) {
                val item=itens.getJSONObject(i)
                val estado=when(item.optString("estado")) {
                    "concluida" -> "Concluída · melhor resultado: ${item.optDouble("melhor_percentual").toInt()}%"
                    "em_andamento" -> "Em andamento · continue estudando"
                    else -> "Disponível para estudar"
                }
                lista.addView(MaterialCardView(this@AtividadesActivity).apply {
                    radius=dp(16).toFloat(); cardElevation=0f
                    strokeWidth=dp(1); strokeColor=getColor(R.color.BordaButton)
                    setCardBackgroundColor(UiPaleta.superficie())
                    isClickable=true; isFocusable=true
                    contentDescription="${item.optString("titulo")}. $estado"
                    layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(11) }
                    addView(LinearLayout(this@AtividadesActivity).apply {
                        orientation=LinearLayout.VERTICAL; setPadding(dp(18),dp(17),dp(18),dp(17))
                        addView(TextView(this@AtividadesActivity).apply {
                            text=item.optString("titulo"); textSize=17f; typeface=Typeface.DEFAULT_BOLD
                            setTextColor(getColor(R.color.TextoPrincipal))
                        })
                        addView(TextView(this@AtividadesActivity).apply {
                            text="${item.optString("materia")} · ${item.optString("conteudo")}"; textSize=14f
                            setTextColor(getColor(R.color.TextoSecundario)); setPadding(0,dp(5),0,0)
                        })
                        addView(TextView(this@AtividadesActivity).apply {
                            text=estado; textSize=14f; setTextColor(getColor(R.color.Destaque))
                            setPadding(0,dp(9),0,0)
                        })
                    })
                    setOnClickListener { abrir(item.optInt("id_atividade")) }
                })
            }
        }
    }

    private fun abrir(id:Int) {
        if (ocupado || id<=0) return
        ocupado=true; progresso.visibility=View.VISIBLE; status.text="Abrindo atividade…"
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("atividades_aluno.php",mapOf(
                    "token" to Sessao.token(this@AtividadesActivity),"acao" to "abrir","id_atividade" to id.toString()))
            }
            ocupado=false; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@AtividadesActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                status.text=r.mensagemErro("Não foi possível abrir a atividade."); return@launch
            }
            val item=json.getJSONObject("atividade")
            startActivity(Intent(this@AtividadesActivity,CapitulosActivity::class.java).apply {
                putExtra("ID_ATIVIDADE",id)
                putExtra("ID_CONTEUDO",item.getInt("id_conteudo"))
                putExtra("CONTEUDO_NOME",item.getString("nome"))
                putExtra("ID_CAPITULO_ABRIR",item.getInt("id_capitulo"))
                putExtra("UNIDADES_JSON",item.getJSONArray("unidades").toString())
                putExtra("ID_TURMA",item.getInt("id_turma"))
                putExtra("MATERIA",item.getString("materia"))
            })
        }
    }
}
