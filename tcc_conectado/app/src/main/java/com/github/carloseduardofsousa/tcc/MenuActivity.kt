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
import android.widget.TextView
import androidx.annotation.DrawableRes
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import com.google.android.material.card.MaterialCardView

class MenuActivity : AppCompatActivity() {
    private lateinit var opcoes: LinearLayout

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.limpar(this); voltarLogin(); return }
        setContentView(R.layout.activity_menu)

        opcoes=findViewById(R.id.containerOpcoesMenu)
        findViewById<TextView>(R.id.tvSaudacaoMenu).text="Olá, ${Sessao.nome(this)}\nO que você deseja fazer?"
        findViewById<MaterialButton>(R.id.btnSairMenu).setOnClickListener {
            Sessao.encerrar(this); finish()
        }

        val perfil=Sessao.tipoPerfil(this)
        findViewById<TextView>(R.id.tvPerfilMenu).text=when(perfil){
            "aluno"->"ÁREA DO ALUNO"; "professor"->"ÁREA DO PROFESSOR"; else->""
        }
        opcao(R.drawable.ic_menu_account,"Minha conta","Veja seus dados e escolha a aparência do aplicativo") { abrir(MinhaContaActivity::class.java) }
        when(perfil){
            "aluno"->{
                opcao(R.drawable.ic_menu_study,"Estudar","Escolha uma matéria, frente e capítulo") { abrir(SelecionarMateriaActivity::class.java) }
                opcao(R.drawable.ic_menu_chart,"Meu desempenho","Acompanhe seus acertos por matéria e capítulo") { abrir(DesempenhoTopicosActivity::class.java) }
                opcao(R.drawable.ic_menu_study,"Meu histórico","Revise suas respostas e retome os capítulos estudados") { abrir(HistoricoActivity::class.java) }
                opcao(R.drawable.ic_menu_trophy,"Ranking da minha série","Veja sua posição e pontuação mensal") { abrirSelecao("RANKING_SERIE") }
            }
            "professor"->{
                opcao(R.drawable.ic_menu_chart,"Gráficos das turmas","Analise dificuldades por matéria e capítulo") { abrirSelecao("GRAFICOS_TURMA") }
                opcao(R.drawable.ic_menu_trophy,"Ranking da escola","Selecione uma série e veja a classificação") { abrirSelecao("RANKING_SERIE") }
                opcao(R.drawable.ic_menu_target,"Personalização de estudo","Oriente os estudos de toda a turma") { abrir(ProfessorActivity::class.java) }
            }
            else->{Sessao.limpar(this);voltarLogin()}
        }
        opcao(R.drawable.ic_menu_web,"Painel da secretaria","Abrir o site de gestão escolar no navegador") { PainelSecretaria.abrir(this) }
    }

    private fun opcao(@DrawableRes icone:Int,titulo:String,descricao:String,click:()->Unit){
        val card=MaterialCardView(this).apply{
            radius=dp(18).toFloat();strokeWidth=dp(1);strokeColor=UiPaleta.hex("#E1E3EE")
            cardElevation=0f;setCardBackgroundColor(UiPaleta.superficie())
            isClickable=true;isFocusable=true;setOnClickListener{click()}
            layoutParams=LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,ViewGroup.LayoutParams.WRAP_CONTENT).apply{bottomMargin=dp(14)}
        }
        val linha=LinearLayout(this).apply{
            orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL
            setPadding(dp(16),dp(17),dp(14),dp(17))
        }
        linha.addView(ImageView(this).apply{
            setImageResource(icone);imageTintList=ColorStateList.valueOf(UiPaleta.hex("#5C6BC0"))
            background=getDrawable(R.drawable.bg_menu_icon);setPadding(dp(12),dp(12),dp(12),dp(12))
        },LinearLayout.LayoutParams(dp(50),dp(50)).apply{marginEnd=dp(14)})
        linha.addView(LinearLayout(this).apply{
            orientation=LinearLayout.VERTICAL
            addView(TextView(this@MenuActivity).apply{text=titulo;textSize=17f;typeface=Typeface.DEFAULT_BOLD;setTextColor(UiPaleta.hex("#1A237E"))})
            addView(TextView(this@MenuActivity).apply{text=descricao;textSize=14f;setTextColor(UiPaleta.hex("#666680"));setPadding(0,dp(4),0,0)})
        },LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f))
        linha.addView(TextView(this).apply{text="›";textSize=30f;gravity=Gravity.CENTER;setTextColor(UiPaleta.hex("#9FA8DA"))},LinearLayout.LayoutParams(dp(28),dp(48)))
        card.addView(linha);opcoes.addView(card)
    }

    private fun dp(valor:Int)=(valor*resources.displayMetrics.density).toInt()
    private fun abrir(clazz:Class<*>)=startActivity(Intent(this,clazz).apply{
        putExtra("ID_USUARIO",Sessao.idUsuario(this@MenuActivity));putExtra("NOME_USUARIO",Sessao.nome(this@MenuActivity));putExtra("ID_TURMA",Sessao.idTurma(this@MenuActivity))
    })
    private fun abrirSelecao(modo:String)=startActivity(Intent(this,SelecionarRelatorioActivity::class.java).putExtra("MODO",modo))
    private fun voltarLogin(){startActivity(Intent(this,LoginActivity::class.java).apply{flags=Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK});finish()}
}
