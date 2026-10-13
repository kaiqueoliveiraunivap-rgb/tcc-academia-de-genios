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

/** Conteúdos pedagógicos publicados para a série real da matrícula. */
class ConteudosActivity : AppCompatActivity() {
    private lateinit var lista: LinearLayout
    private lateinit var aviso: TextView
    private lateinit var progresso: ProgressBar
    private var ocupado=false
    private var versaoProgresso=ProgressoAluno.versao
    private val materia get()=intent.getStringExtra("MATERIA").orEmpty()
    private fun dp(n:Int)=(n*resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this) || Sessao.ehProfessor(this) || materia.isBlank()) { finish(); return }
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            setPadding(dp(22),dp(20),dp(22),dp(18))
            setBackgroundColor(getColor(R.color.BackGround))
        }
        root.addView(TextView(this).apply {
            text="‹  Matérias"; textSize=16f; setTextColor(getColor(R.color.Destaque))
            gravity=Gravity.CENTER_VERTICAL; minHeight=dp(48); setOnClickListener { finish() }
        })
        root.addView(TextView(this).apply {
            text=materia; textSize=27f; typeface=Typeface.DEFAULT_BOLD
            setTextColor(getColor(R.color.TextoPrincipal))
            setPadding(0,dp(14),0,dp(4))
        })
        root.addView(TextView(this).apply {
            text="Escolha um conteúdo para aprender e praticar."
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
        })
        progresso=ProgressBar(this).apply { visibility=View.GONE }
        root.addView(progresso,LinearLayout.LayoutParams(-2,-2).apply { topMargin=dp(18) })
        aviso=TextView(this).apply {
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
            setPadding(0,dp(12),0,dp(12))
        }
        root.addView(aviso)
        lista=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { isFillViewport=true; addView(lista) },LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root)
        setContentView(root)
        carregar()
    }

    override fun onResume() {
        super.onResume()
        if (::lista.isInitialized && versaoProgresso!=ProgressoAluno.versao) {
            versaoProgresso=ProgressoAluno.versao
            carregar()
        }
    }

    private fun carregar() {
        if (ocupado) return
        ocupado=true; progresso.visibility=View.VISIBLE; aviso.text="Carregando conteúdos…"
        lista.removeAllViews()
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("catalogo_estudos.php",mapOf(
                    "token" to Sessao.token(this@ConteudosActivity),
                    "acao" to "conteudos", "materia" to materia,
                    "id_turma" to Sessao.idTurma(this@ConteudosActivity).toString()
                ))
            }
            ocupado=false; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ConteudosActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                aviso.text=r.mensagemErro("Não foi possível carregar os conteúdos.")
                lista.addView(EstudoUi.botao(this@ConteudosActivity,"Tentar novamente") { carregar() })
                return@launch
            }
            val itens=json.optJSONArray("conteudos")
            if (itens==null || itens.length()==0) {
                aviso.text="A secretaria ainda está organizando os conteúdos desta matéria para sua série."
                return@launch
            }
            aviso.text="${json.optString("serie")} · ${itens.length()} conteúdos disponíveis"
            var ultimoGrupo=""
            for (i in 0 until itens.length()) {
                val item=itens.getJSONObject(i)
                val bimestre=item.optInt("bimestre",0)
                val grupo=if (bimestre in 1..4) "${bimestre}º bimestre" else "Outros conteúdos"
                if (grupo!=ultimoGrupo) {
                    ultimoGrupo=grupo
                    lista.addView(TextView(this@ConteudosActivity).apply {
                        text=grupo.uppercase(); textSize=12f; typeface=Typeface.DEFAULT_BOLD
                        letterSpacing=.1f; setTextColor(getColor(R.color.Destaque))
                        setPadding(0,dp(18),0,dp(10))
                    })
                }
                val estado=item.optString("estado")
                val descricao=when(estado) {
                    "concluido" -> "Concluído · ${item.optInt("tentativas")} tentativa(s)"
                    "em_andamento" -> "Em andamento · continue de onde parou"
                    else -> "Ainda não iniciado"
                }
                lista.addView(MaterialCardView(this@ConteudosActivity).apply {
                    radius=dp(16).toFloat(); cardElevation=0f
                    strokeWidth=dp(1); strokeColor=getColor(R.color.BordaButton)
                    setCardBackgroundColor(UiPaleta.superficie())
                    isClickable=true; isFocusable=true; contentDescription="${item.optString("nome")}. $descricao"
                    layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(10) }
                    addView(LinearLayout(this@ConteudosActivity).apply {
                        orientation=LinearLayout.VERTICAL; setPadding(dp(17),dp(16),dp(17),dp(16))
                        addView(TextView(this@ConteudosActivity).apply {
                            text=item.optString("nome"); textSize=17f; typeface=Typeface.DEFAULT_BOLD
                            setTextColor(getColor(R.color.TextoPrincipal))
                        })
                        addView(TextView(this@ConteudosActivity).apply {
                            text=descricao; textSize=14f; setTextColor(getColor(R.color.TextoSecundario))
                            setPadding(0,dp(6),0,0)
                        })
                    })
                    setOnClickListener { abrir(item.optInt("id_conteudo")) }
                })
            }
        }
    }

    private fun abrir(id:Int) {
        if (ocupado || id<=0) return
        ocupado=true; progresso.visibility=View.VISIBLE; aviso.text="Abrindo conteúdo…"
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("catalogo_estudos.php",mapOf(
                    "token" to Sessao.token(this@ConteudosActivity), "acao" to "abrir",
                    "id_conteudo" to id.toString(),
                    "id_turma" to Sessao.idTurma(this@ConteudosActivity).toString()
                ))
            }
            ocupado=false; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ConteudosActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                aviso.text=r.mensagemErro("Não foi possível abrir este conteúdo.")
                return@launch
            }
            val conteudo=json.getJSONObject("conteudo")
            startActivity(Intent(this@ConteudosActivity,CapitulosActivity::class.java).apply {
                putExtra("ID_CONTEUDO",id)
                putExtra("CONTEUDO_NOME",conteudo.getString("nome"))
                putExtra("ID_CAPITULO_ABRIR",conteudo.getInt("id_capitulo"))
                putExtra("ID_TURMA",Sessao.idTurma(this@ConteudosActivity))
                putExtra("MATERIA",materia)
            })
        }
    }
}
