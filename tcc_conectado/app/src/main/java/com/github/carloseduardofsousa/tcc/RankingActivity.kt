package com.github.carloseduardofsousa.tcc

import android.os.Bundle
import android.view.View
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONArray

class RankingActivity : AppCompatActivity() {

    private lateinit var progressBar:    ProgressBar
    private lateinit var containerLista: LinearLayout
    private lateinit var txtTituloMes:   TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ranking)

        progressBar    = findViewById(R.id.progressBarRanking)
        containerLista = findViewById(R.id.containerRanking)
        txtTituloMes   = findViewById(R.id.txtTituloMes)

        val idTurma = intent.getIntExtra("ID_TURMA", 1)
        val mesRef  = intent.getStringExtra("MES_REF") ?: ""

        txtTituloMes.text = "Ranking - $mesRef"
        carregarRanking(idTurma, mesRef)
    }

    private fun carregarRanking(idTurma: Int, mesRef: String) {
        progressBar.visibility = View.VISIBLE

        Thread {
            val resposta = ApiClient.post("listar_ranking.php", mapOf(
                "token"          to Sessao.token(this),
                "id_turma"       to idTurma.toString(),
                "mes_referencia" to mesRef
            ))

            runOnUiThread {
                progressBar.visibility = View.GONE

                if (resposta == null) {
                    Toast.makeText(this, "Erro ao carregar ranking.", Toast.LENGTH_LONG).show()
                    return@runOnUiThread
                }

                val json = try { JSONArray(resposta) } catch (e: Exception) { JSONArray() }

                if (json.length() == 0) {
                    val tv = TextView(this)
                    tv.text = "Nenhum dado de ranking ainda."
                    tv.textSize = 16f
                    containerLista.addView(tv)
                    return@runOnUiThread
                }

                for (i in 0 until json.length()) {
                    val item  = json.getJSONObject(i)
                    val nome  = item.getString("nome")
                    val pontos = item.getInt("pontos")
                    val pos   = i + 1

                    val tv = TextView(this)
                    val medalha = when (pos) { 1 -> "🥇"; 2 -> "🥈"; 3 -> "🥉"; else -> "$pos." }
                    tv.text     = "  $medalha  $nome — $pontos pts"
                    tv.textSize = 18f
                    tv.setPadding(0, 16, 0, 16)
                    containerLista.addView(tv)

                    // Divisor
                    val div = View(this)
                    div.setBackgroundColor(0xFFDDDDDD.toInt())
                    div.layoutParams = LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 1)
                    containerLista.addView(div)
                }
            }
        }.start()
    }
}
