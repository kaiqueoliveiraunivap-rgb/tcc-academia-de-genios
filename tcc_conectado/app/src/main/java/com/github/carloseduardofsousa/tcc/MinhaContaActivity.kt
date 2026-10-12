package com.github.carloseduardofsousa.tcc

import android.os.Bundle
import android.view.View
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButtonToggleGroup
import com.google.android.material.card.MaterialCardView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class MinhaContaActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); finish(); return }
        setContentView(R.layout.activity_minha_conta)
        EstudoUi.protegerBarras(findViewById(R.id.raizMinhaConta))
        findViewById<View>(R.id.btnVoltarConta).setOnClickListener { finish() }

        val temas=findViewById<MaterialButtonToggleGroup>(R.id.grupoTema)
        temas.check(if (TemaApp.escuro()) R.id.btnTemaEscuro else R.id.btnTemaClaro)
        temas.addOnButtonCheckedListener { _, checkedId, checked ->
            if (checked && checkedId != -1 && (checkedId == R.id.btnTemaEscuro) != TemaApp.escuro()) {
                TemaApp.selecionar(this, checkedId == R.id.btnTemaEscuro)
            }
        }
        carregarConta()
    }

    private fun carregarConta() {
        val progresso=findViewById<ProgressBar>(R.id.progressoConta)
        val estado=findViewById<TextView>(R.id.estadoConta)
        val cartao=findViewById<MaterialCardView>(R.id.cartaoConta)
        progresso.visibility=View.VISIBLE
        estado.visibility=View.GONE
        lifecycleScope.launch {
            val resposta=withContext(Dispatchers.IO) {
                ApiClient.postComStatus("minha_conta.php",mapOf("token" to Sessao.token(this@MinhaContaActivity)))
            }
            progresso.visibility=View.GONE
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@MinhaContaActivity); finish(); return@launch }
            val json=runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status")!="sucesso") {
                estado.text=resposta.mensagemErro("Não foi possível carregar sua conta.")
                estado.visibility=View.VISIBLE
                return@launch
            }
            val u=json.getJSONObject("usuario")
            val nome=u.getString("nome")
            findViewById<TextView>(R.id.avatarConta).text=nome.trim().firstOrNull()?.uppercase() ?: "?"
            findViewById<TextView>(R.id.nomeConta).text=nome
            findViewById<TextView>(R.id.emailConta).text=u.getString("email")
            findViewById<TextView>(R.id.perfilConta).text=if(u.getString("tipo_perfil")=="professor") "PROFESSOR(A)" else "ALUNO(A)"
            findViewById<TextView>(R.id.idConta).text="Identificação na escola: ${u.getInt("id_usuario")}"
            val turmas=json.getJSONArray("turmas")
            val resumo=(0 until turmas.length()).map { i ->
                val t=turmas.getJSONObject(i)
                listOf(t.optString("nome_turma"),t.optString("serie"),t.optString("ano_letivo"))
                    .filter { it.isNotBlank() }.joinToString(" · ")
            }
            findViewById<TextView>(R.id.turmasConta).text=if(resumo.isEmpty()) "Nenhuma turma vinculada no momento." else "Turmas: ${resumo.joinToString("; ")}"
            cartao.visibility=View.VISIBLE
        }
    }
}
