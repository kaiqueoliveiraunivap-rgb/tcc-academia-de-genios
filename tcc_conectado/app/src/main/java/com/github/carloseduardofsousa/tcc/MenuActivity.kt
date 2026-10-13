package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.content.res.ColorStateList
import android.graphics.Color
import android.graphics.Typeface
import android.os.Bundle
import android.view.Gravity
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.annotation.DrawableRes
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class MenuActivity : AppCompatActivity() {
    private lateinit var opcoes: LinearLayout
    private var painelAluno: LinearLayout? = null
    private var versaoProgresso=ProgressoAluno.versao

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.limpar(this); voltarLogin(); return }
        setContentView(R.layout.activity_menu)

        opcoes=findViewById(R.id.containerOpcoesMenu)
        findViewById<TextView>(R.id.tvSaudacaoMenu).text="Olá, ${Sessao.nome(this).substringBefore(' ')}!"
        findViewById<ImageView>(R.id.btnMinhaContaMenu).setOnClickListener { abrir(MinhaContaActivity::class.java) }
        findViewById<MaterialButton>(R.id.btnSairMenu).setOnClickListener {
            Sessao.encerrar(this); finish()
        }

        val perfil=Sessao.tipoPerfil(this)
        findViewById<TextView>(R.id.tvPerfilMenu).text=when(perfil){
            "aluno"->"ÁREA DO ALUNO"; "professor"->"ÁREA DO PROFESSOR"; else->""
        }
        when(perfil){
            "aluno"->{
                painelAluno=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
                opcoes.addView(painelAluno,0)
                opcao(R.drawable.ic_menu_study,"Estudar","Matérias e conteúdos da sua série") { abrir(SelecionarMateriaActivity::class.java) }
                opcao(R.drawable.ic_menu_chart,"Meu progresso","Resultados, dificuldades e histórico") { abrir(DesempenhoTopicosActivity::class.java) }
                opcao(R.drawable.ic_menu_target,"Atividades","Estudos enviados para sua turma") { abrir(AtividadesActivity::class.java) }
                opcao(R.drawable.ic_menu_trophy,"Ranking","Classificação mensal da sua série") { abrirSelecao("RANKING_SERIE") }
                carregarPainelAluno()
            }
            "professor"->{
                opcao(R.drawable.ic_menu_chart,"Gráficos das turmas","Analise dificuldades por matéria e capítulo") { abrirSelecao("GRAFICOS_TURMA") }
                opcao(R.drawable.ic_menu_trophy,"Ranking da escola","Selecione uma série e veja a classificação") { abrirSelecao("RANKING_SERIE") }
                opcao(R.drawable.ic_menu_target,"Personalização de estudo","Oriente os estudos de toda a turma") { abrir(ProfessorActivity::class.java) }
            }
            else->{Sessao.limpar(this);voltarLogin()}
        }
    }

    override fun onResume() {
        super.onResume()
        if (painelAluno!=null && versaoProgresso!=ProgressoAluno.versao) {
            versaoProgresso=ProgressoAluno.versao
            carregarPainelAluno()
        }
    }

    private fun carregarPainelAluno() {
        val painel=painelAluno ?: return
        painel.removeAllViews()
        painel.addView(TextView(this).apply {
            text="Carregando seu progresso…"; textSize=15f
            setTextColor(getColor(R.color.TextoSecundario)); setPadding(0,dp(14),0,dp(20))
        })
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("painel_aluno.php",mapOf("token" to Sessao.token(this@MenuActivity)))
            }
            if (r.sessaoExpirada) { Sessao.encerrar(this@MenuActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            painel.removeAllViews()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                painel.addView(TextView(this@MenuActivity).apply {
                    text=r.mensagemErro("Não foi possível carregar o progresso. Toque para tentar novamente.")
                    textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
                    minHeight=dp(60); setOnClickListener { carregarPainelAluno() }
                })
                return@launch
            }
            val serie=json.optString("serie").takeIf { it.isNotBlank() && it!="null" }
            val turma=json.optString("turma").takeIf { it.isNotBlank() && it!="null" }
            findViewById<TextView>(R.id.tvPerfilMenu).text=listOfNotNull(serie,turma).distinct().joinToString(" · ").ifBlank { "ÁREA DO ALUNO" }
            val continuar=json.optJSONObject("continuar")
            val principal=MaterialCardView(this@MenuActivity).apply {
                radius=dp(18).toFloat(); cardElevation=0f
                setCardBackgroundColor(getColor(R.color.DestaqueSuave))
                layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(13) }
                isClickable=true; isFocusable=true
                addView(LinearLayout(this@MenuActivity).apply {
                    orientation=LinearLayout.VERTICAL; setPadding(dp(20),dp(19),dp(20),dp(19))
                    addView(TextView(this@MenuActivity).apply {
                        text="CONTINUAR ESTUDANDO"; textSize=12f; typeface=Typeface.DEFAULT_BOLD
                        letterSpacing=.08f; setTextColor(getColor(R.color.Destaque))
                    })
                    addView(TextView(this@MenuActivity).apply {
                        text=continuar?.optString("nome") ?: "Comece por uma matéria"
                        textSize=20f; typeface=Typeface.DEFAULT_BOLD
                        setTextColor(getColor(R.color.TextoPrincipal)); setPadding(0,dp(7),0,dp(5))
                    })
                    addView(TextView(this@MenuActivity).apply {
                        text=continuar?.optString("materia") ?: "Explore os conteúdos disponíveis para sua série."
                        textSize=14f; setTextColor(getColor(R.color.TextoSecundario))
                    })
                })
                setOnClickListener {
                    if (continuar!=null) abrirConteudo(continuar)
                    else abrir(SelecionarMateriaActivity::class.java)
                }
            }
            painel.addView(principal)
            val total=json.optInt("conteudos_disponiveis")
            val feitos=json.optInt("conteudos_concluidos")
            val quizzes=json.optInt("quizzes_realizados")
            val media=if (json.isNull("aproveitamento_medio")) "Sem resultados ainda"
                else "${json.optDouble("aproveitamento_medio").toInt()}% de aproveitamento médio"
            painel.addView(MaterialCardView(this@MenuActivity).apply {
                radius=dp(16).toFloat(); cardElevation=0f
                strokeWidth=dp(1); strokeColor=getColor(R.color.BordaButton)
                setCardBackgroundColor(UiPaleta.superficie())
                layoutParams=LinearLayout.LayoutParams(-1,-2).apply { bottomMargin=dp(16) }
                addView(LinearLayout(this@MenuActivity).apply {
                    orientation=LinearLayout.VERTICAL; setPadding(dp(19),dp(17),dp(19),dp(17))
                    addView(TextView(this@MenuActivity).apply {
                        text="SEU PROGRESSO"; textSize=12f; typeface=Typeface.DEFAULT_BOLD
                        letterSpacing=.08f; setTextColor(getColor(R.color.Destaque))
                    })
                    addView(TextView(this@MenuActivity).apply {
                        text=if(total>0) "$feitos de $total conteúdos concluídos" else "Conteúdos em preparação"
                        textSize=17f; typeface=Typeface.DEFAULT_BOLD
                        setTextColor(getColor(R.color.TextoPrincipal)); setPadding(0,dp(8),0,dp(9))
                    })
                    if (total>0) addView(ProgressBar(this@MenuActivity,null,android.R.attr.progressBarStyleHorizontal).apply {
                        max=total; progress=feitos; contentDescription="$feitos de $total conteúdos concluídos"
                    },LinearLayout.LayoutParams(-1,dp(8)).apply { bottomMargin=dp(11) })
                    addView(TextView(this@MenuActivity).apply {
                        text="$quizzes quizzes realizados · $media\n${json.optInt("atividades_pendentes")} atividades pendentes"
                        textSize=14f; setTextColor(getColor(R.color.TextoSecundario))
                    })
                })
            })
        }
    }

    private fun abrirConteudo(conteudo:JSONObject) {
        lifecycleScope.launch {
            val id=conteudo.optInt("id_conteudo")
            val r=withContext(Dispatchers.IO) {
                val params=mutableMapOf(
                    "token" to Sessao.token(this@MenuActivity),"acao" to "abrir",
                    "id_conteudo" to id.toString())
                Sessao.idTurma(this@MenuActivity).takeIf { it>0 }?.let { params["id_turma"]=it.toString() }
                ApiClient.postComStatus("catalogo_estudos.php",params)
            }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (r.sessaoExpirada) { Sessao.encerrar(this@MenuActivity); return@launch }
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                android.widget.Toast.makeText(this@MenuActivity,r.mensagemErro("Não foi possível retomar o estudo."),android.widget.Toast.LENGTH_LONG).show()
                return@launch
            }
            startActivity(Intent(this@MenuActivity,CapitulosActivity::class.java).apply {
                putExtra("ID_CONTEUDO",id); putExtra("CONTEUDO_NOME",conteudo.optString("nome"))
                putExtra("ID_CAPITULO_ABRIR",json.getJSONObject("conteudo").getInt("id_capitulo"))
                putExtra("UNIDADES_JSON",json.getJSONObject("conteudo").getJSONArray("unidades").toString())
                putExtra("ID_TURMA",Sessao.idTurma(this@MenuActivity))
                putExtra("MATERIA",conteudo.optString("materia"))
            })
        }
    }

    private fun opcao(@DrawableRes icone:Int,titulo:String,descricao:String,click:()->Unit){
        val card=MaterialCardView(this).apply{
            radius=dp(16).toFloat();strokeWidth=dp(1);strokeColor=UiPaleta.hex("#E1E3EE")
            cardElevation=0f;setCardBackgroundColor(UiPaleta.superficie())
            isClickable=true;isFocusable=true;setOnClickListener{click()}
            layoutParams=LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,ViewGroup.LayoutParams.WRAP_CONTENT).apply{bottomMargin=dp(12)}
        }
        val linha=LinearLayout(this).apply{
            orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL
            setPadding(dp(18),dp(19),dp(15),dp(19))
        }
        linha.addView(ImageView(this).apply{
            setImageResource(icone);imageTintList=ColorStateList.valueOf(UiPaleta.hex("#5C6BC0"))
            background=getDrawable(R.drawable.bg_menu_icon);setPadding(dp(12),dp(12),dp(12),dp(12))
        },LinearLayout.LayoutParams(dp(50),dp(50)).apply{marginEnd=dp(16)})
        linha.addView(LinearLayout(this).apply{
            orientation=LinearLayout.VERTICAL
            addView(TextView(this@MenuActivity).apply{text=titulo;textSize=16f;typeface=Typeface.DEFAULT_BOLD;setTextColor(UiPaleta.hex("#1A237E"))})
            addView(TextView(this@MenuActivity).apply{text=descricao;textSize=13f;setTextColor(UiPaleta.hex("#666680"));setPadding(0,dp(5),0,0)})
        },LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f))
        linha.addView(TextView(this).apply{text="›";textSize=27f;gravity=Gravity.CENTER;setTextColor(UiPaleta.hex("#9FA8DA"))},LinearLayout.LayoutParams(dp(24),dp(48)))
        card.addView(linha);opcoes.addView(card)
    }

    private fun dp(valor:Int)=(valor*resources.displayMetrics.density).toInt()
    private fun abrir(clazz:Class<*>)=startActivity(Intent(this,clazz).apply{
        putExtra("ID_USUARIO",Sessao.idUsuario(this@MenuActivity));putExtra("NOME_USUARIO",Sessao.nome(this@MenuActivity));putExtra("ID_TURMA",Sessao.idTurma(this@MenuActivity))
    })
    private fun abrirSelecao(modo:String)=startActivity(Intent(this,SelecionarRelatorioActivity::class.java).putExtra("MODO",modo))
    private fun voltarLogin(){startActivity(Intent(this,LoginActivity::class.java).apply{flags=Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK});finish()}
}
