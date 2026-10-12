package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class FrentesActivity : AppCompatActivity() {
    private lateinit var lista: LinearLayout
    private lateinit var status: TextView
    private lateinit var progresso: ProgressBar
    private var versaoProgresso = ProgressoAluno.versao

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); finish(); return }
        val densidade=resources.displayMetrics.density
        val root=LinearLayout(this).apply {
            orientation=LinearLayout.VERTICAL
            val p=(24*densidade).toInt(); setPadding(p,p,p,p)
            setBackgroundColor(UiPaleta.hex("#FAFAFA"))
        }
        root.addView(TextView(this).apply { text="‹  Voltar"; textSize=16f; setTextColor(getColor(R.color.Destaque)); minHeight=EstudoUi.dp(this@FrentesActivity,48); gravity=android.view.Gravity.CENTER_VERTICAL; setOnClickListener { finish() } })
        root.addView(TextView(this).apply {
            text=intent.getStringExtra("MATERIA") ?: "Frentes"; textSize=27f
            typeface=Typeface.DEFAULT_BOLD; setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,18,0,4)
        })
        root.addView(TextView(this).apply { text="Escolha a frente do livro"; textSize=16f; setTextColor(UiPaleta.hex("#666680")); setPadding(0,0,0,18) })
        status=TextView(this).apply { textSize=16f; setTextColor(UiPaleta.hex("#666680")) }
        root.addView(status)
        progresso=ProgressBar(this)
        root.addView(progresso)
        lista=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { addView(lista) },LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root)
        setContentView(root)
        carregar()
    }

    override fun onResume() {
        super.onResume()
        if (::lista.isInitialized && versaoProgresso != ProgressoAluno.versao) {
            versaoProgresso = ProgressoAluno.versao; carregar()
        }
    }

    private fun carregar() {
        progresso.visibility=View.VISIBLE; status.text="Carregando frentes…"; lista.removeAllViews()
        lifecycleScope.launch {
            val resposta=withContext(Dispatchers.IO) { ApiClient.postComStatus("listar_frentes.php",mapOf(
                "token" to Sessao.token(this@FrentesActivity),
                "materia" to intent.getStringExtra("MATERIA").orEmpty()
            )) }
            progresso.visibility=View.GONE
            if(resposta.sessaoExpirada){Sessao.encerrar(this@FrentesActivity);finish();return@launch}
            val json=runCatching{JSONObject(resposta.corpo.orEmpty())}.getOrNull()
            if(!resposta.sucesso || json?.optString("status")!="sucesso"){
                status.text=resposta.mensagemErro("Não foi possível carregar as frentes.")
                lista.addView(Button(this@FrentesActivity).apply{text="Tentar novamente";setOnClickListener{carregar()}})
                return@launch
            }
            val frentes=json.getJSONArray("frentes")
            status.text=if(frentes.length()==0) "Ainda não há livros disponíveis para esta matéria." else ""
            status.visibility=if(frentes.length()==0) View.VISIBLE else View.GONE
            for(i in 0 until frentes.length()){
                val frente=frentes.getJSONObject(i)
                lista.addView(LinearLayout(this@FrentesActivity).apply {
                    orientation=LinearLayout.VERTICAL
                    val p = EstudoUi.dp(context, 16); setPadding(p,p,p,p)
                    minimumHeight = EstudoUi.dp(context, 64)
                    background=GradientDrawable().apply{setColor(UiPaleta.superficie());cornerRadius=20f;setStroke(2,UiPaleta.hex("#E4E6F2"))}
                    layoutParams=LinearLayout.LayoutParams(-1,-2).apply{setMargins(0,12,0,4)}
                    addView(TextView(this@FrentesActivity).apply{text=EstudoUi.nomeLivro(frente.getString("livro"));textSize=18f;typeface=Typeface.DEFAULT_BOLD;setTextColor(UiPaleta.hex("#1A237E"))})
                    addView(TextView(this@FrentesActivity).apply{text=frente.getString("titulo");textSize=16f;setTextColor(UiPaleta.hex("#455A64"));setPadding(0,6,0,0)})
                    val total = frente.getInt("total_capitulos"); val concluidos = frente.optInt("concluidos")
                    addView(TextView(this@FrentesActivity).apply{text="$concluidos de $total capítulos concluídos";textSize=16f;setTextColor(UiPaleta.hex("#455A64"));setPadding(0,6,0,0)})
                    addView(ProgressBar(this@FrentesActivity, null, android.R.attr.progressBarStyleHorizontal).apply {
                        max = maxOf(1, total); progress = concluidos
                        contentDescription = "$concluidos de $total capítulos concluídos"
                    }, LinearLayout.LayoutParams(-1, EstudoUi.dp(context, 10)).apply { topMargin = EstudoUi.dp(context, 10) })
                    setOnClickListener {
                        startActivity(Intent(this@FrentesActivity,CapitulosActivity::class.java).apply {
                            this@FrentesActivity.intent.extras?.let{putExtras(it)}
                            putExtra("ID_FRENTE",frente.getInt("id_frente"));putExtra("FRENTE",frente.getString("titulo"))
                        })
                    }
                    isClickable=true;isFocusable=true
                })
            }
        }
    }

}
