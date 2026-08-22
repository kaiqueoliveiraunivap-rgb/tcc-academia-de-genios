package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class ResultadoActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_resultado)

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

        txtPlacar.text   = "$acertos / $total"
        txtMensagem.text = when {
            percentual == 100 -> "Perfeito! \uD83C\uDFC6"
            percentual >= 70  -> "Muito bem! \uD83D\uDE04"
            percentual >= 50  -> "Quase la! \uD83D\uDCAA"
            else              -> "Continue praticando! \uD83D\uDCDA"
        }

        btnRanking.setOnClickListener {
            val intent = Intent(this, RankingActivity::class.java)
            intent.putExtra("ID_TURMA", idTurma)
            intent.putExtra("MES_REF",  mesRef)
            startActivity(intent)
        }

        btnGraficos.setOnClickListener {
            val intent = Intent(this, GraficosActivity::class.java)
            intent.putExtra("ID_USUARIO",   idUsuario)
            intent.putExtra("NOME_USUARIO", nomeUsuario)
            startActivity(intent)
        }
    }
}
