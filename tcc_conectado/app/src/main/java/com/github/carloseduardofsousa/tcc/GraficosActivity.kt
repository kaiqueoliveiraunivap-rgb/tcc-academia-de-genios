package com.github.carloseduardofsousa.tcc

import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONArray

class GraficosActivity : AppCompatActivity() {

    private lateinit var progressBar:    ProgressBar
    private lateinit var containerLista: LinearLayout
    private lateinit var txtTitulo:      TextView
    private lateinit var txtResumo:      TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_graficos)

        progressBar    = findViewById(R.id.progressBarGraficos)
        containerLista = findViewById(R.id.containerGraficos)
        txtTitulo      = findViewById(R.id.txtTituloGraficos)
        txtResumo      = findViewById(R.id.txtResumoGraficos)

        val idUsuario   = intent.getIntExtra("ID_USUARIO", 0)
        val nomeUsuario = intent.getStringExtra("NOME_USUARIO") ?: "Aluno"

        txtTitulo.text = "Histórico de $nomeUsuario"
        carregarHistorico(idUsuario)
    }

    private fun carregarHistorico(idUsuario: Int) {
        progressBar.visibility = View.VISIBLE

        Thread {
            // O histórico devolvido é o do dono do token; o servidor não lê
            // id_aluno da requisição.
            val resposta = ApiClient.post("listar_historico.php", mapOf("token" to Sessao.token(this)))

            runOnUiThread {
                progressBar.visibility = View.GONE

                if (resposta == null) {
                    Toast.makeText(this, "Erro ao carregar histórico.", Toast.LENGTH_LONG).show()
                    return@runOnUiThread
                }

                val json = try { JSONArray(resposta) } catch (e: Exception) { JSONArray() }

                if (json.length() == 0) {
                    val tv = TextView(this)
                    tv.text    = "Nenhuma resposta registrada ainda."
                    tv.textSize = 16f
                    containerLista.addView(tv)
                    return@runOnUiThread
                }

                var totalAcertos = 0
                val total = json.length()

                for (i in 0 until total) {
                    val item      = json.getJSONObject(i)
                    val enunciado = item.getString("enunciado")
                    val respAluno = item.getString("alternativa_escolhida")
                    val acertou   = item.getInt("acertou") == 1
                    if (acertou) totalAcertos++

                    // Card de cada questão
                    val card = LinearLayout(this)
                    card.orientation = LinearLayout.VERTICAL
                    card.setPadding(24, 20, 24, 20)
                    card.setBackgroundColor(if (acertou) 0xFFE8F5E9.toInt() else 0xFFFFEBEE.toInt())
                    val lp = LinearLayout.LayoutParams(
                        LinearLayout.LayoutParams.MATCH_PARENT,
                        LinearLayout.LayoutParams.WRAP_CONTENT
                    )
                    lp.setMargins(0, 0, 0, 12)
                    card.layoutParams = lp

                    val tvEnunciado = TextView(this)
                    tvEnunciado.text     = enunciado
                    tvEnunciado.textSize = 14f
                    tvEnunciado.setTextColor(0xFF333333.toInt())

                    val tvResposta = TextView(this)
                    tvResposta.text     = "${if (acertou) "✓" else "✗"}  $respAluno"
                    tvResposta.textSize = 13f
                    tvResposta.setTextColor(if (acertou) 0xFF2E7D32.toInt() else 0xFFC62828.toInt())
                    tvResposta.setPadding(0, 6, 0, 0)

                    card.addView(tvEnunciado)
                    card.addView(tvResposta)
                    containerLista.addView(card)
                }

                // Resumo no topo
                val pct = ((totalAcertos.toFloat() / total) * 100).toInt()
                txtResumo.text = "$totalAcertos/$total acertos  ($pct%)"
                txtResumo.visibility = View.VISIBLE
            }
        }.start()
    }   
}
