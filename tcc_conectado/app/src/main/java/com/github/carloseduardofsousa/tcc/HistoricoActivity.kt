package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

class HistoricoActivity : AppCompatActivity() {
    private val itens = mutableListOf<JSONObject>()
    private lateinit var status: TextView
    private lateinit var carregando: ProgressBar
    private lateinit var botao: android.widget.Button
    private lateinit var lista: RecyclerView
    private var cursor = 0
    private var carregou = false
    private var ocupado = false
    private var versao = ProgressoAluno.versao

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this) || Sessao.tipoPerfil(this) != "aluno") { finish(); return }
        val root = EstudoUi.raiz(this)
        root.addView(EstudoUi.botao(this, "← Voltar") { finish() })
        root.addView(EstudoUi.texto(this, "Meu histórico", 26f, true))
        status = EstudoUi.texto(this, "Carregando suas tentativas…"); root.addView(status)
        carregando = ProgressBar(this); root.addView(carregando)
        lista = RecyclerView(this).apply {
            layoutManager = LinearLayoutManager(this@HistoricoActivity)
            adapter = HistoricoAdapter()
        }
        root.addView(lista, LinearLayout.LayoutParams(-1, 0, 1f))
        botao = EstudoUi.botao(this, "Carregar mais") { carregar() }; root.addView(botao)
        setContentView(root)
        // O histórico é pequeno por página; restaura sem consultar a rede na rotação.
        savedInstanceState?.getString("itens")?.let { raw ->
            val array = JSONArray(raw)
            for (i in 0 until array.length()) itens.add(array.getJSONObject(i))
            cursor = savedInstanceState.getInt("cursor")
            carregou = savedInstanceState.getBoolean("carregou")
            versao = savedInstanceState.getInt("versao", -1)
        }
        if (carregou) mostrarEstado() else carregar()
    }

    override fun onResume() {
        super.onResume()
        if (::lista.isInitialized && versao != ProgressoAluno.versao && !ocupado) {
            val tamanho = itens.size; itens.clear(); lista.adapter?.notifyItemRangeRemoved(0, tamanho)
            cursor = 0; carregou = false; versao = ProgressoAluno.versao; carregar()
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        // Limita o Bundle: páginas adicionais continuam disponíveis no banco.
        outState.putString("itens", JSONArray(itens.take(20)).toString())
        outState.putInt("cursor", if (itens.size > 20) itens[19].getInt("id_quiz") else cursor)
        outState.putBoolean("carregou", carregou)
        outState.putInt("versao", versao)
    }

    private fun mostrarEstado() {
        carregando.visibility = View.GONE
        status.text = if (itens.isEmpty()) "Você ainda não concluiu um quiz. Escolha uma matéria para começar." else "Da mais recente à mais antiga. Toque para revisar ou refazer."
        botao.visibility = if (!carregou || cursor > 0 || itens.isEmpty()) View.VISIBLE else View.GONE
        botao.isEnabled = true
        botao.text = if (itens.isEmpty() && carregou) "Começar a estudar" else "Carregar mais"
        botao.setOnClickListener {
            if (itens.isEmpty() && carregou) startActivity(Intent(this, SelecionarMateriaActivity::class.java)) else carregar()
        }
    }

    private fun carregar() {
        if (ocupado) return
        ocupado = true; carregando.visibility = View.VISIBLE; botao.isEnabled = false
        val params = mutableMapOf("token" to Sessao.token(this))
        if (cursor > 0) params["antes_id"] = cursor.toString()
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) { ApiClient.postComStatus("historico_estudos.php", params) }
            ocupado = false; carregando.visibility = View.GONE
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@HistoricoActivity); return@launch }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                status.text = resposta.mensagemErro("Não foi possível carregar o histórico.")
                botao.visibility = View.VISIBLE; botao.isEnabled = true; botao.text = "Tentar novamente"
                botao.setOnClickListener { carregar() }; return@launch
            }
            val array = json.getJSONArray("historico"); val inicio = itens.size
            for (i in 0 until array.length()) itens.add(array.getJSONObject(i))
            lista.adapter?.notifyItemRangeInserted(inicio, array.length())
            cursor = json.optInt("proximo_cursor", 0); carregou = true; mostrarEstado()
        }
    }

    private inner class HistoricoAdapter : RecyclerView.Adapter<HistoricoAdapter.Holder>() {
        inner class Holder(val card: LinearLayout, val titulo: TextView, val detalhe: TextView) : RecyclerView.ViewHolder(card)
        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): Holder {
            val card = EstudoUi.cartao(this@HistoricoActivity).apply {
                layoutParams = RecyclerView.LayoutParams(-1, -2).apply { bottomMargin = EstudoUi.dp(context, 12) }
                isClickable = true; isFocusable = true
            }
            val titulo = EstudoUi.texto(this@HistoricoActivity, "", 18f, true)
            val detalhe = EstudoUi.texto(this@HistoricoActivity, "")
            card.addView(titulo); card.addView(detalhe)
            return Holder(card, titulo, detalhe)
        }
        override fun getItemCount() = itens.size
        override fun onBindViewHolder(holder: Holder, position: Int) {
            val item = itens[position]
            val acertos = item.getInt("acertos"); val total = item.getInt("total")
            holder.titulo.text = "${item.getString("materia")} · ${item.getString("capitulo")}"
            holder.detalhe.text = "${item.optString("frente", "")} · ${item.getString("livro")}\n${EstudoUi.data(item.getString("criado_em"))}\n$acertos de $total acertos · ${EstudoUi.percentual(acertos, total)}%\nRevisar respostas ›"
            holder.card.setOnClickListener {
                startActivity(Intent(this@HistoricoActivity, RevisaoQuizActivity::class.java).putExtra("ID_QUIZ", item.getInt("id_quiz")))
            }
        }
    }
}
