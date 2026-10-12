package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import android.view.View
import androidx.appcompat.app.AppCompatActivity

class ResultadoActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_resultado)
        EstudoUi.protegerBarras(findViewById(R.id.resultadoScroll))

        val acertos     = intent.getIntExtra("ACERTOS", 0)
        val total       = intent.getIntExtra("TOTAL", 0)
        val idUsuario   = intent.getIntExtra("ID_USUARIO", 0)
        val nomeUsuario = intent.getStringExtra("NOME_USUARIO") ?: ""
        val idTurma     = intent.getIntExtra("ID_TURMA", 1)
        val mesRef      = intent.getStringExtra("MES_REF") ?: ""
        val percentual  = if (total > 0) ((acertos.toFloat() / total) * 100).toInt() else 0

        val txtPlacar   = findViewById<TextView>(R.id.txtPlacar)
        val txtMensagem = findViewById<TextView>(R.id.txtMensagem)
        val btnRanking  = findViewById<Button>(R.id.btnRanking)
        val btnGraficos = findViewById<Button>(R.id.btnGraficos)
        val btnMenu     = findViewById<Button>(R.id.btnMenu)
        val btnRevisar = findViewById<Button>(R.id.btnRevisar)
        val idQuiz = intent.getIntExtra("ID_QUIZ", 0)
        btnRevisar.visibility = if (idQuiz > 0) View.VISIBLE else View.GONE
        btnRevisar.setOnClickListener {
            startActivity(Intent(this, RevisaoQuizActivity::class.java).putExtra("ID_QUIZ", idQuiz))
        }
        findViewById<Button>(R.id.btnHistorico).setOnClickListener {
            startActivity(Intent(this, HistoricoActivity::class.java))
        }

        txtPlacar.text   = "$acertos / $total"
        txtMensagem.text = when {
            percentual == 100 -> "Perfeito! \uD83C\uDFC6"
            percentual >= 70  -> "Muito bem! \uD83D\uDE04"
            percentual >= 50  -> "Quase la! \uD83D\uDCAA"
            else              -> "Continue praticando! \uD83D\uDCDA"
        }

        btnRanking.setOnClickListener {
            startActivity(Intent(this, RankingActivity::class.java).apply {
                putExtra("ID_TURMA",idTurma)
                putExtra("MES_REF",mesRef)
            })
        }

        btnGraficos.setOnClickListener {
            startActivity(Intent(this,DesempenhoTopicosActivity::class.java).apply {
                putExtra("ID_USUARIO",idUsuario)
                putExtra("NOME_USUARIO",nomeUsuario)
                putExtra("ID_TURMA",idTurma)
            })
        }

        btnMenu.setOnClickListener {
            startActivity(Intent(this, MenuActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            })
            finish()
        }
    }
}
