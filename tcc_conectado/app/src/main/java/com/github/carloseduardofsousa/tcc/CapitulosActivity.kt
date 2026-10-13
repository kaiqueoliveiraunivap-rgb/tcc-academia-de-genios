package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Bundle
import android.os.Build
import android.text.Layout
import android.view.View
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import org.json.JSONArray

class CapitulosActivity : AppCompatActivity() {
    private lateinit var lista: LinearLayout
    private lateinit var status: TextView
    private lateinit var progresso: ProgressBar
    private var estudo: String? = null
    private var leitura: String? = null
    private var ocupado = false
    private lateinit var preparacao: PrepararEstudoModel
    private var versaoProgresso = ProgressoAluno.versao
    private fun dp(valor: Int) = (valor * resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); finish(); return }
        preparacao = ViewModelProvider(this)[PrepararEstudoModel::class.java]
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            val p = dp(20)
            setPadding(p,p,p,p)
            setBackgroundColor(UiPaleta.hex("#F5F7FA"))
        }
        root.addView(TextView(this).apply { text="‹  Voltar"; textSize=16f; setTextColor(getColor(R.color.Destaque)); minHeight=EstudoUi.dp(this@CapitulosActivity,48); gravity=android.view.Gravity.CENTER_VERTICAL; setOnClickListener { finish() } })
        root.addView(TextView(this).apply {
            text = intent.getStringExtra("CONTEUDO_NOME") ?: intent.getStringExtra("FRENTE") ?: "Capítulos"
            textSize = 26f; typeface = Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,dp(18),0,dp(4))
        })
        root.addView(TextView(this).apply {
            text = intent.getStringExtra("MATERIA").orEmpty()
            textSize = 15f; setTextColor(UiPaleta.hex("#666680"))
        })
        status = TextView(this).apply { textSize = 16f; setTextColor(UiPaleta.hex("#455A64")); setPadding(0,dp(16),0,dp(10)) }
        root.addView(status)
        progresso = ProgressBar(this)
        root.addView(progresso)
        lista = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        root.addView(ScrollView(this).apply { addView(lista) }, LinearLayout.LayoutParams(-1,0,1f))
        EstudoUi.protegerBarras(root)
        setContentView(root)
        estudo = savedInstanceState?.getString("estudo")
        leitura = savedInstanceState?.getString("leitura")
        val abrir = intent.getIntExtra("ID_CAPITULO_ABRIR", 0)
        if (estudo != null) mostrarEstudo(JSONObject(estudo!!))
        else if (leitura != null) mostrarLeitura(JSONObject(leitura!!))
        else if (preparacao.estado.value.idCapitulo > 0) { /* A geração continua ao girar a tela. */ }
        else if (savedInstanceState != null && savedInstanceState.getInt("capitulo_pendente", 0) > 0) {
            mostrarFalhaGeracao(savedInstanceState.getInt("capitulo_pendente"), savedInstanceState.getString("titulo_pendente").orEmpty(), "A preparação foi interrompida. Toque em tentar novamente para continuar neste capítulo.")
        } else if (abrir > 0) {
            intent.removeExtra("ID_CAPITULO_ABRIR")
            if (intent.getIntExtra("ID_CONTEUDO",0)>0) gerar(abrir,intent.getStringExtra("CONTEUDO_NOME").orEmpty())
            else lerCapitulo(abrir)
        } else carregar()
        lifecycleScope.launch {
            repeatOnLifecycle(Lifecycle.State.STARTED) {
                preparacao.estado.collect { estado ->
                    if (estado.idCapitulo <= 0) return@collect
                    intent.putExtra("ID_CAPITULO_SELECIONADO", estado.idCapitulo)
                    if (estado.carregando) {
                        lista.removeAllViews(); progresso.visibility = View.VISIBLE
                        val etapa = when (estado.fase) {
                            "preparando_material" -> "Preparando o material"
                            "gerando_conteudo" -> "Gerando a explicação e as questões"
                            "validando_resposta" -> "Validando o estudo"
                            "finalizando" -> "Finalizando"
                            "concluido" -> "Abrindo o estudo"
                            "erro" -> "A geração falhou"
                            else -> "Aguardando processamento"
                        }
                        status.text = "$etapa: ${estado.titulo}\nNa primeira preparação, isso pode levar alguns minutos."
                    } else estado.resposta?.let { resposta ->
                        progresso.visibility = View.GONE
                        if (resposta.sessaoExpirada) { Sessao.encerrar(this@CapitulosActivity); return@collect }
                        val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
                        if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                            mostrarFalhaGeracao(estado.idCapitulo, estado.titulo, resposta.mensagemErro("Não foi possível preparar este capítulo. Tente novamente."))
                        } else if (estudo != resposta.corpo) {
                            estudo = resposta.corpo; mostrarEstudo(json)
                        }
                    }
                }
            }
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putString("estudo", estudo)
        outState.putString("leitura", leitura)
        outState.putInt("capitulo_pendente", preparacao.estado.value.idCapitulo)
        outState.putString("titulo_pendente", preparacao.estado.value.titulo)
    }

    private fun gerar(id: Int, titulo: String) {
        intent.putExtra("ID_CAPITULO_SELECIONADO",id)
        preparacao.gerar(Sessao.token(this), id, titulo,
            intent.getIntExtra("ID_TURMA", Sessao.idTurma(this)),intent.getIntExtra("ID_CONTEUDO",0))
    }

    private fun mostrarFalhaGeracao(id: Int, titulo: String, mensagem: String) {
        progresso.visibility = View.GONE; lista.removeAllViews()
        status.text = "$titulo\n$mensagem"
        lista.addView(EstudoUi.botao(this, "Tentar novamente") { gerar(id, titulo) })
        lista.addView(EstudoUi.botao(this, "Ler material original") { preparacao.limpar(); lerCapitulo(id) })
        lista.addView(EstudoUi.botao(this, "Voltar aos conteúdos") { finish() })
    }

    override fun onResume() {
        super.onResume()
        if (::preparacao.isInitialized && versaoProgresso != ProgressoAluno.versao) {
            versaoProgresso = ProgressoAluno.versao
            if (estudo == null && preparacao.estado.value.idCapitulo == 0 && !ocupado) carregar()
        }
    }

    private fun requisitar(endpoint: String, params: Map<String,String>, pronto: (JSONObject) -> Unit) {
        if (ocupado) return
        ocupado = true
        progresso.visibility = View.VISIBLE
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus(endpoint, params + ("token" to Sessao.token(this@CapitulosActivity)))
            }
            ocupado = false
            progresso.visibility = View.GONE
            if (resposta.sessaoExpirada) {
                startActivity(Intent(this@CapitulosActivity, LoginActivity::class.java).apply {
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                })
                return@launch
            }
            val json = runCatching { JSONObject(resposta.corpo ?: "") }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                status.text = resposta.mensagemErro("Não foi possível carregar os capítulos.")
                if (lista.childCount == 0) lista.addView(Button(this@CapitulosActivity).apply {
                    text = "Tentar novamente"; setOnClickListener { carregar() }
                })
            } else pronto(json)
        }
    }

    private fun carregar() {
        lista.removeAllViews()
        status.text = "Carregando capítulos…"
        requisitar("listar_capitulos.php", mapOf("id_frente" to intent.getIntExtra("ID_FRENTE",0).toString())) { json ->
            val capitulos = json.getJSONArray("capitulos")
            val concluidos = json.optInt("concluidos")
            status.text = if (capitulos.length() == 0) "Ainda não há capítulos disponíveis nesta frente." else "$concluidos de ${capitulos.length()} capítulos concluídos\nConclua o quiz para registrar seu progresso."
            if (capitulos.length() > 0) lista.addView(ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply {
                max = capitulos.length(); progress = concluidos
                contentDescription = "$concluidos de ${capitulos.length()} capítulos concluídos"
            }, LinearLayout.LayoutParams(-1, dp(12)).apply { bottomMargin = dp(12) })
            for (i in 0 until capitulos.length()) {
                val c = capitulos.getJSONObject(i)
                lista.addView(MaterialCardView(this).apply {
                    radius = dp(16).toFloat()
                    strokeWidth = dp(1)
                    strokeColor = UiPaleta.hex("#E1E3EE")
                    cardElevation = 0f
                    setCardBackgroundColor(UiPaleta.superficie())
                    layoutParams = LinearLayout.LayoutParams(-1,-2).apply { setMargins(0,0,0,dp(12)) }
                    addView(LinearLayout(this@CapitulosActivity).apply {
                        orientation = LinearLayout.VERTICAL
                        setPadding(dp(18),dp(17),dp(18),dp(17))
                        addView(TextView(this@CapitulosActivity).apply {
                            text = c.getString("titulo")
                            textSize = 17f
                            typeface = Typeface.DEFAULT_BOLD
                            setTextColor(UiPaleta.hex("#1A237E"))
                        })
                        addView(TextView(this@CapitulosActivity).apply {
                            text = if (c.optBoolean("concluido")) "✓ Concluído · melhor resultado: ${c.optDouble("melhor_percentual").toInt()}%" else "Toque para estudar · ainda não concluído"
                            textSize = 14f
                            setTextColor(UiPaleta.hex("#666680"))
                            setPadding(0,dp(6),0,0)
                        })
                    })
                    setOnClickListener { if (!ocupado) lerCapitulo(c.getInt("id_capitulo")) }
                    isClickable = true
                    isFocusable = true
                })
            }
        }
    }

    private fun lerCapitulo(id: Int) {
        status.text = "Abrindo páginas do livro…"
        requisitar("ler_capitulo.php", mapOf("id_capitulo" to id.toString())) { json ->
            leitura = json.toString()
            mostrarLeitura(json)
        }
    }

    private fun mostrarLeitura(json: JSONObject) {
        progresso.visibility = View.GONE
        lista.removeAllViews()
        status.text = "${EstudoUi.nomeLivro(json.getString("livro"))}\nTexto original do capítulo"
        val paginas = json.getJSONArray("paginas")
        lista.addView(TextView(this).apply {
            text = "${json.getString("capitulo")} · ${paginas.length()} páginas"
            textSize = 21f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E"))
            setPadding(0,dp(12),0,dp(18))
        })
        for (i in 0 until paginas.length()) {
            val pagina = paginas.getJSONObject(i)
            lista.addView(LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(dp(18),dp(18),dp(18),dp(18))
                background = GradientDrawable().apply {
                    setColor(UiPaleta.superficie())
                    cornerRadius = dp(16).toFloat()
                    setStroke(dp(1),UiPaleta.hex("#E1E3EE"))
                }
                layoutParams = LinearLayout.LayoutParams(-1,-2).apply { bottomMargin = dp(12) }
                addView(TextView(this@CapitulosActivity).apply {
                    text = "PÁGINA ${pagina.optInt("pagina")}"
                    textSize = 12f
                    typeface = Typeface.DEFAULT_BOLD
                    setTextColor(getColor(R.color.Destaque))
                    setPadding(0,0,0,dp(10))
                })
                addView(TextView(this@CapitulosActivity).apply {
                    text = pagina.optString("texto").trim()
                    textSize = 16f
                    setTextColor(UiPaleta.hex("#1A1A2E"))
                    setLineSpacing(dp(5).toFloat(),1f)
                    setTextIsSelectable(true)
                })
            })
        }
        if (json.optBoolean("ia_disponivel")) {
            lista.addView(EstudoUi.botao(this,"Preparar explicação e quiz com IA") {
                leitura = null
                gerar(json.getInt("id_capitulo"), json.getString("capitulo"))
            })
        } else {
            lista.addView(TextView(this).apply {
                text = "O quiz será liberado quando a escola ativar a geração de questões. Você já pode ler o capítulo completo acima."
                textSize = 15f
                setTextColor(UiPaleta.hex("#666680"))
                setPadding(0,dp(12),0,dp(18))
            })
        }
        lista.addView(EstudoUi.botao(this,"Abrir PDF original") {
            runCatching {
                val fonte=java.net.URL(java.net.URL(ApiConfig.BASE_URL),json.getString("fonte_url")).toString()
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(fonte)))
            }.onFailure { Toast.makeText(this,"Nenhum navegador disponível",Toast.LENGTH_SHORT).show() }
        })
        adicionarProximaParte(json.getInt("id_capitulo"))
        lista.addView(EstudoUi.botao(this,"Voltar aos conteúdos") { leitura = null; if(intent.getIntExtra("ID_CONTEUDO",0)>0) finish() else carregar() })
    }

    private fun mostrarEstudo(json: JSONObject) {
        intent.putExtra("ID_TURMA", json.optInt("id_turma", Sessao.idTurma(this)))
        progresso.visibility = View.GONE
        lista.removeAllViews()
        val unidades=unidadesPedagogicas()
        val indice=unidades.indexOf(intent.getIntExtra("ID_CAPITULO_SELECIONADO",0))
        status.text = intent.getStringExtra("CONTEUDO_NOME")?.let {
            if(unidades.size>1 && indice>=0) "$it · Parte ${indice+1} de ${unidades.size}" else it
        } ?: "${EstudoUi.nomeLivro(json.getString("livro"))}\n${json.getString("capitulo")}"
        val dados = json.getJSONObject("estudo")
        val resumo = dados.getJSONArray("resumo")
        lista.addView(TextView(this).apply {
            text = "Aprender"
            textSize = 22f; typeface = Typeface.DEFAULT_BOLD
            setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,dp(12),0,dp(12))
        })
        for (i in 0 until resumo.length()) {
            val p = resumo.getJSONObject(i)
            lista.addView(LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(dp(18),dp(18),dp(18),dp(16))
                background = GradientDrawable().apply {
                    setColor(UiPaleta.superficie()); cornerRadius = dp(16).toFloat()
                    setStroke(dp(1),UiPaleta.hex("#E4E6F2"))
                }
                layoutParams = LinearLayout.LayoutParams(-1,-2).apply { setMargins(0,0,0,dp(12)) }
                if (p.optString("titulo").isNotBlank()) addView(TextView(this@CapitulosActivity).apply {
                    text = p.getString("titulo"); textSize = 19f; typeface = Typeface.DEFAULT_BOLD
                    setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,0,0,dp(10))
                })
                addView(TextView(this@CapitulosActivity).apply {
                    text = p.getString("texto").trim()
                    textSize = 17f; setTextColor(UiPaleta.hex("#1A1A2E"))
                    setLineSpacing(dp(5).toFloat(),1f); setTextIsSelectable(true)
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) justificationMode = Layout.JUSTIFICATION_MODE_INTER_WORD
                })
                val topicos = p.optJSONArray("topicos")
                if (topicos != null) for (j in 0 until topicos.length()) {
                    addView(LinearLayout(this@CapitulosActivity).apply {
                        orientation = LinearLayout.HORIZONTAL; setPadding(0,dp(10),0,0)
                        addView(TextView(this@CapitulosActivity).apply {
                            text = "•"; textSize = 18f; setTextColor(UiPaleta.hex("#5C6BC0"))
                        }, LinearLayout.LayoutParams(dp(20),-2))
                        addView(TextView(this@CapitulosActivity).apply {
                            text = topicos.getString(j); textSize = 17f
                            setTextColor(UiPaleta.hex("#1A1A2E")); setLineSpacing(dp(4).toFloat(),1f)
                            setTextIsSelectable(true)
                        }, LinearLayout.LayoutParams(0,-2,1f))
                    })
                }
                addView(TextView(this@CapitulosActivity).apply {
                    text = "Fonte: página ${p.getInt("pagina")} do livro"
                    textSize = 13f; setTextColor(UiPaleta.hex("#5C6BC0"))
                    setPadding(0,dp(12),0,0)
                })
            })
        }
        val palavrasChave = dados.optJSONArray("palavras_chave")
        if (palavrasChave != null && palavrasChave.length() > 0) {
            lista.addView(LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL; setPadding(dp(18),dp(18),dp(18),dp(18))
                background = GradientDrawable().apply { setColor(UiPaleta.hex("#E8EAF6")); cornerRadius = dp(16).toFloat() }
                layoutParams = LinearLayout.LayoutParams(-1,-2).apply { setMargins(0,0,0,dp(16)) }
                addView(TextView(this@CapitulosActivity).apply {
                    text = "Palavras-chave do capítulo"; textSize = 20f; typeface = Typeface.DEFAULT_BOLD
                    setTextColor(UiPaleta.hex("#1A237E")); setPadding(0,0,0,dp(10))
                })
                addView(TextView(this@CapitulosActivity).apply {
                    text = (0 until palavrasChave.length()).joinToString("  •  ") { palavrasChave.getString(it) }
                    textSize = 16f; setTextColor(UiPaleta.hex("#303F9F")); setLineSpacing(dp(6).toFloat(),1f)
                    setTextIsSelectable(true)
                })
            })
        }
        lista.addView(Button(this).apply {
            text = "Praticar · quiz de 5 questões"
            isAllCaps = false
            backgroundTintList = android.content.res.ColorStateList.valueOf(UiPaleta.hex("#5C6BC0"))
            setTextColor(Color.WHITE)
            setOnClickListener {
                startActivity(Intent(this@CapitulosActivity, MainActivity::class.java).apply {
                    this@CapitulosActivity.intent.extras?.let { putExtras(it) }
                    putExtra("QUESTOES_JSON", dados.getJSONArray("questoes").toString())
                    putExtra("ID_ESTUDO", json.getInt("id_estudo"))
                    putExtra("ID_CAPITULO", intent.getIntExtra("ID_CAPITULO_SELECIONADO", 0))
                    putExtra("MATERIA", intent.getStringExtra("MATERIA"))
                })
            }
        })
        lista.addView(Button(this).apply {
            text = "Abrir livro original"
            isAllCaps = false
            setOnClickListener {
                runCatching {
                    val fonte = java.net.URL(java.net.URL(ApiConfig.BASE_URL), json.getString("fonte_url")).toString()
                    startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(fonte)))
                }
                    .onFailure { Toast.makeText(this@CapitulosActivity,"Nenhum navegador disponível",Toast.LENGTH_SHORT).show() }
            }
        })
        adicionarProximaParte(intent.getIntExtra("ID_CAPITULO_SELECIONADO",0))
        lista.addView(Button(this).apply { text = "Voltar aos conteúdos"; setOnClickListener { estudo = null; preparacao.limpar(); if(intent.getIntExtra("ID_CONTEUDO",0)>0) finish() else carregar() } })
    }

    private fun unidadesPedagogicas():List<Int> = runCatching {
        val a=JSONArray(intent.getStringExtra("UNIDADES_JSON") ?: "[]")
        (0 until a.length()).map { a.getInt(it) }
    }.getOrDefault(emptyList())

    private fun adicionarProximaParte(idAtual:Int) {
        val unidades=unidadesPedagogicas()
        val indice=unidades.indexOf(idAtual)
        if (indice<0 || indice>=unidades.lastIndex) return
        lista.addView(EstudoUi.botao(this,"Próxima parte do conteúdo") {
            val idSeguinte=unidades[indice+1]
            val idConteudo=intent.getIntExtra("ID_CONTEUDO",0)
            if (idConteudo<=0 || ocupado) return@botao
            ocupado=true; progresso.visibility=View.VISIBLE; status.text="Abrindo próxima parte…"
            lifecycleScope.launch {
                val r=withContext(Dispatchers.IO) {
                    ApiClient.postComStatus("catalogo_estudos.php",mapOf(
                        "token" to Sessao.token(this@CapitulosActivity),"acao" to "avancar",
                        "id_conteudo" to idConteudo.toString(),"id_capitulo" to idSeguinte.toString(),
                        "id_turma" to intent.getIntExtra("ID_TURMA",Sessao.idTurma(this@CapitulosActivity)).toString()))
                }
                ocupado=false; progresso.visibility=View.GONE
                if (r.sessaoExpirada) { Sessao.encerrar(this@CapitulosActivity); return@launch }
                val resposta=runCatching { JSONObject(r.corpo.orEmpty()) }.getOrNull()
                if (!r.sucesso || resposta?.optString("status")!="sucesso") {
                    status.text=r.mensagemErro("Não foi possível avançar. Tente novamente.")
                    return@launch
                }
                estudo=null; leitura=null; preparacao.limpar()
                gerar(idSeguinte,intent.getStringExtra("CONTEUDO_NOME").orEmpty())
            }
        })
    }
}
