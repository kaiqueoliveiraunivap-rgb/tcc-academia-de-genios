package com.github.carloseduardofsousa.tcc

import android.graphics.Typeface
import android.os.Bundle
import android.text.InputFilter
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class ProfessorConteudoDetalheActivity : AppCompatActivity() {
    private lateinit var status:TextView
    private lateinit var orientacao:EditText
    private lateinit var desempenho:LinearLayout
    private lateinit var atividades:LinearLayout
    private lateinit var progresso:ProgressBar
    private lateinit var salvar:Button
    private lateinit var publicar:Button
    private var rascunhoRestaurado=false
    private var ocupado=false
    private val idTurma get()=intent.getIntExtra("ID_TURMA",0)
    private val idConteudo get()=intent.getIntExtra("ID_CONTEUDO",0)
    private fun dp(n:Int)=(n*resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.ehProfessor(this) || idTurma<=0 || idConteudo<=0) { finish(); return }
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL; setPadding(dp(22),dp(20),dp(22),dp(20))
            setBackgroundColor(getColor(R.color.BackGround))
        }
        root.addView(TextView(this).apply {
            text="‹  Conteúdos"; textSize=16f; minHeight=dp(48); gravity=Gravity.CENTER_VERTICAL
            setTextColor(getColor(R.color.Destaque)); setOnClickListener { finish() }
        })
        root.addView(TextView(this).apply {
            text=intent.getStringExtra("NOME_CONTEUDO").orEmpty(); textSize=27f
            typeface=Typeface.DEFAULT_BOLD; setTextColor(getColor(R.color.TextoPrincipal))
            setPadding(0,dp(12),0,dp(3))
        })
        root.addView(TextView(this).apply {
            text=intent.getStringExtra("MATERIA").orEmpty(); textSize=15f
            setTextColor(getColor(R.color.TextoSecundario))
        })
        progresso=ProgressBar(this).apply { visibility=View.GONE }
        root.addView(progresso,LinearLayout.LayoutParams(-2,-2).apply { topMargin=dp(16) })
        status=TextView(this).apply {
            textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
            setPadding(0,dp(12),0,dp(10))
        }
        root.addView(status)
        val conteudo=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { isFillViewport=true; addView(conteudo) },LinearLayout.LayoutParams(-1,0,1f))
        conteudo.addView(titulo("Preparar estudo"))
        conteudo.addView(TextView(this).apply {
            text="Descreva o que a turma precisa aprender. A explicação e o quiz usarão os livros revisados."
            textSize=14f; setTextColor(getColor(R.color.TextoSecundario))
        })
        orientacao=EditText(this).apply {
            hint="Ex.: explique as raízes da função e mostre exemplos resolvidos antes das questões."
            inputType=InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_MULTI_LINE
            minLines=4; maxLines=8; filters=arrayOf(InputFilter.LengthFilter(1000))
            setTextColor(getColor(R.color.TextoPrincipal))
            setHintTextColor(getColor(R.color.TextoSecundario))
            setPadding(dp(15),dp(12),dp(15),dp(12))
            setBackgroundColor(UiPaleta.superficie())
        }
        conteudo.addView(orientacao,LinearLayout.LayoutParams(-1,-2).apply { topMargin=dp(9); bottomMargin=dp(10) })
        salvar=Button(this).apply { text="Salvar orientação"; isAllCaps=false; setOnClickListener { salvarOrientacao() } }
        conteudo.addView(salvar,LinearLayout.LayoutParams(-1,dp(52)))
        publicar=Button(this).apply { text="Publicar atividade para a turma"; isAllCaps=false; setOnClickListener { dialogoPublicar() } }
        conteudo.addView(publicar,LinearLayout.LayoutParams(-1,dp(52)).apply { topMargin=dp(10) })
        conteudo.addView(titulo("Desempenho de aprendizagem"))
        desempenho=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        conteudo.addView(desempenho)
        conteudo.addView(titulo("Atividades publicadas"))
        atividades=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        conteudo.addView(atividades)
        EstudoUi.protegerBarras(root); setContentView(root)
        if (savedInstanceState!=null) {
            orientacao.setText(savedInstanceState.getString("rascunho"))
            rascunhoRestaurado=true
        }
        carregar()
    }

    override fun onSaveInstanceState(outState:Bundle) {
        super.onSaveInstanceState(outState)
        outState.putString("rascunho",orientacao.text.toString())
    }

    private fun titulo(texto:String)=TextView(this).apply {
        text=texto; textSize=20f; typeface=Typeface.DEFAULT_BOLD
        setTextColor(getColor(R.color.TextoPrincipal)); setPadding(0,dp(24),0,dp(9))
    }

    private fun texto(texto:String)=TextView(this).apply {
        text=texto; textSize=15f; setTextColor(getColor(R.color.TextoSecundario))
        setPadding(0,dp(5),0,dp(7))
    }

    private fun parametros(acao:String)=mapOf(
        "token" to Sessao.token(this),"acao" to acao,
        "id_turma" to idTurma.toString(),"id_conteudo" to idConteudo.toString())

    private fun carregar() {
        if (ocupado) return
        ocupado=true; progresso.visibility=View.VISIBLE; status.text="Carregando dados do conteúdo…"
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("professor_pedagogico.php",parametros("detalhe"))
            }
            ocupado=false; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ProfessorConteudoDetalheActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (!r.sucesso || json?.optString("status")!="sucesso") {
                status.text=r.mensagemErro("Não foi possível carregar o conteúdo.")
                return@launch
            }
            status.text="Orientação e resultados desta turma"
            if (!rascunhoRestaurado) orientacao.setText(json.optJSONObject("orientacao")?.optString("instrucao").orEmpty())
            rascunhoRestaurado=false
            desempenho.removeAllViews(); atividades.removeAllViews()
            val d=json.getJSONObject("desempenho")
            val respostas=d.optInt("respostas")
            desempenho.addView(texto(if(respostas==0) "Nenhum quiz realizado neste conteúdo até agora."
                else "${d.optInt("alunos")} ${if (d.optInt("alunos")==1) "aluno participante" else "alunos participantes"} · $respostas ${if (respostas==1) "resposta" else "respostas"} · ${d.optDouble("aproveitamento").toInt()}% de acertos"))
            val dificuldades=json.getJSONArray("dificuldades")
            if (dificuldades.length()>0) {
                desempenho.addView(texto("Assuntos com mais erros:"))
                for (i in 0 until dificuldades.length()) {
                    val item=dificuldades.getJSONObject(i)
                    val erros=item.optInt("respostas")-item.optInt("acertos")
                    desempenho.addView(texto("${item.optString("assunto")} · $erros erros em ${item.optInt("respostas")} respostas"))
                }
                val assunto=dificuldades.getJSONObject(0).optString("assunto")
                desempenho.addView(Button(this@ProfessorConteudoDetalheActivity).apply {
                    text="Preparar reforço"; isAllCaps=false
                    setOnClickListener {
                        orientacao.setText("Retome $assunto com exemplos resolvidos e exercícios graduais. Destaque os erros mais comuns antes do quiz.")
                        orientacao.requestFocus()
                    }
                })
            }
            val publicadas=json.getJSONArray("atividades")
            if (publicadas.length()==0) atividades.addView(texto("Nenhuma atividade publicada para esta turma."))
            for (i in 0 until publicadas.length()) {
                val a=publicadas.getJSONObject(i)
                atividades.addView(texto("${a.optString("titulo")} · ${a.optInt("concluintes")} alunos concluíram"))
            }
        }
    }

    private fun salvarOrientacao() {
        val texto=orientacao.text.toString().trim()
        if (texto.isEmpty()) { orientacao.error="Escreva a orientação."; return }
        if (ocupado) return
        ocupado=true; salvar.isEnabled=false; progresso.visibility=View.VISIBLE
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("professor_pedagogico.php",parametros("salvar_orientacao")+("instrucao" to texto))
            }
            ocupado=false; salvar.isEnabled=true; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ProfessorConteudoDetalheActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            status.text=if (r.sucesso && json?.optString("status")=="sucesso") "Orientação ativa para esta turma e conteúdo."
                else r.mensagemErro("Não foi possível salvar a orientação.")
        }
    }

    private fun dialogoPublicar() {
        val campo=EditText(this).apply {
            hint="Título da atividade"; filters=arrayOf(InputFilter.LengthFilter(180))
            setTextColor(getColor(R.color.TextoPrincipal))
            setPadding(dp(20),dp(14),dp(20),dp(14))
        }
        AlertDialog.Builder(this).setTitle("Publicar atividade")
            .setMessage("O quiz deste conteúdo ficará disponível para os alunos desta turma.")
            .setView(campo).setNegativeButton("Cancelar",null)
            .setPositiveButton("Publicar") { _,_ -> publicar(campo.text.toString().trim()) }.show()
    }

    private fun publicar(titulo:String) {
        if (titulo.isEmpty()) { status.text="Informe o título da atividade."; return }
        if (ocupado) return
        ocupado=true; publicar.isEnabled=false; progresso.visibility=View.VISIBLE
        lifecycleScope.launch {
            val r=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("professor_pedagogico.php",parametros("publicar_atividade")+("titulo" to titulo))
            }
            ocupado=false; publicar.isEnabled=true; progresso.visibility=View.GONE
            if (r.sessaoExpirada) { Sessao.encerrar(this@ProfessorConteudoDetalheActivity); return@launch }
            val json=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
            if (r.sucesso && json?.optString("status")=="sucesso") {
                status.text="Atividade publicada para a turma."; carregar()
            } else status.text=r.mensagemErro("Não foi possível publicar a atividade.")
        }
    }
}
