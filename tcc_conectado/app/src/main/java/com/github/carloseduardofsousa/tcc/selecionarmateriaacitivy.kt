package com.github.carloseduardofsousa.tcc

import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

// ── Modelo ───────────────────────────────────────────────────
data class MateriaItem(val nome: String, val sigla: String, val capitulos: Int, val concluidos: Int)

// ── Adapter do grid de matérias ──────────────────────────────
class MateriaAdapter(
    private val items: List<MateriaItem>,
    private val onClick: (MateriaItem) -> Unit
) : RecyclerView.Adapter<MateriaAdapter.VH>() {

    private var selectedPos = -1

    inner class VH(val card: LinearLayout) : RecyclerView.ViewHolder(card)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val ctx = parent.context
        val sigla = TextView(ctx).apply {
            textSize = 15f
            typeface = android.graphics.Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
            setTextColor(ContextCompat.getColor(ctx, R.color.Destaque))
            background = GradientDrawable().apply {
                cornerRadius = EstudoUi.dp(ctx, 12).toFloat()
                setColor(ContextCompat.getColor(ctx, R.color.DestaqueSuave))
            }
        }
        val label = TextView(ctx).apply {
            textSize = 16f
            typeface = android.graphics.Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER_VERTICAL
        }
        val status = TextView(ctx).apply { textSize = 13f; gravity = Gravity.CENTER_VERTICAL }
        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            minimumHeight = EstudoUi.dp(ctx, 82)
            isFocusable = true
            setPadding(EstudoUi.dp(ctx, 18), EstudoUi.dp(ctx, 12), EstudoUi.dp(ctx, 18), EstudoUi.dp(ctx, 12))
            layoutParams = ViewGroup.MarginLayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { setMargins(0, EstudoUi.dp(ctx, 5), 0, EstudoUi.dp(ctx, 5)) }
            addView(sigla, LinearLayout.LayoutParams(EstudoUi.dp(ctx, 48), EstudoUi.dp(ctx, 48)).apply { rightMargin = EstudoUi.dp(ctx, 14) })
            addView(LinearLayout(ctx).apply { orientation = LinearLayout.VERTICAL; addView(label); addView(status) }, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        }
        return VH(card)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val item = items[position]
        val isSelected = position == selectedPos

        (h.card.getChildAt(0) as TextView).text = item.sigla
        val textos = h.card.getChildAt(1) as LinearLayout
        (textos.getChildAt(0) as TextView).apply {
            text = item.nome
            setTextColor(if (isSelected) Color.WHITE else UiPaleta.hex("#1A1A2E"))
        }
        (textos.getChildAt(1) as TextView).apply { text = "${item.concluidos} de ${item.capitulos} conteúdos concluídos"; setTextColor(if (isSelected) Color.WHITE else UiPaleta.hex("#666680")) }
        h.card.background = GradientDrawable().apply {
            cornerRadius = EstudoUi.dp(h.card.context, 16).toFloat()
            setColor(if (isSelected) UiPaleta.hex("#5C6BC0") else UiPaleta.superficie())
            setStroke(EstudoUi.dp(h.card.context, 1), UiPaleta.hex("#E1E3EE"))
        }
        h.card.isEnabled = item.capitulos > 0
        h.card.alpha = if (item.capitulos > 0) 1f else .64f
        h.card.setOnClickListener {
            val pos = h.bindingAdapterPosition
            if (pos == RecyclerView.NO_POSITION || items[pos].capitulos == 0) return@setOnClickListener

            val prev = selectedPos
            selectedPos = pos
            if (prev != RecyclerView.NO_POSITION) notifyItemChanged(prev)
            notifyItemChanged(selectedPos)
            onClick(items[pos])
        }
    }

    override fun getItemCount() = items.size
    fun selecionar(posicao: Int) {
        selectedPos = posicao
        if (posicao in items.indices) notifyItemChanged(posicao)
    }
}

// ── Activity ─────────────────────────────────────────────────
class SelecionarMateriaActivity : AppCompatActivity() {

    private var materiaSelecionada: MateriaItem? = null
    private lateinit var btnIniciar: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var tvCarregando: TextView
    private var falhou = false
    private var carregando = false
    private var materiaRestaurada: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!Sessao.estaLogado(this)) { Sessao.encerrar(this); finish(); return }
        setContentView(R.layout.activity_selecionar_materia)
        EstudoUi.protegerBarras(findViewById(R.id.raizMaterias))
        findViewById<TextView>(R.id.btnVoltarMaterias).setOnClickListener { finish() }

        // Liga as views ao XML pelo ID
        btnIniciar    = findViewById(R.id.btnIniciar)
        progressBar   = findViewById(R.id.progressBar)
        tvCarregando  = findViewById(R.id.tvCarregando)

        val rvMaterias = findViewById<RecyclerView>(R.id.rvMaterias)
        rvMaterias.layoutManager = androidx.recyclerview.widget.LinearLayoutManager(this)
        materiaRestaurada = savedInstanceState?.getString("materia")

        btnIniciar.text = "Ver conteúdos"
        btnIniciar.setOnClickListener { if (falhou) carregarMaterias(rvMaterias) else abrirConteudos() }

        carregarMaterias(rvMaterias)
    }

    private fun carregarMaterias(lista: RecyclerView) {
        if (carregando) return
        carregando = true; falhou = false; btnIniciar.isEnabled = false
        progressBar.visibility = View.VISIBLE
        tvCarregando.apply { text = "Carregando matérias disponíveis…"; visibility = View.VISIBLE }
        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("catalogo_estudos.php", mapOf("token" to Sessao.token(this@SelecionarMateriaActivity)))
            }
            progressBar.visibility = View.GONE
            carregando = false
            if (resposta.sessaoExpirada) { Sessao.encerrar(this@SelecionarMateriaActivity); finish(); return@launch }
            val json = runCatching { JSONObject(resposta.corpo.orEmpty()) }.getOrNull()
            if (!resposta.sucesso || json?.optString("status") != "sucesso") {
                tvCarregando.text = resposta.mensagemErro("Não foi possível carregar as matérias.")
                falhou = true; btnIniciar.isEnabled = true; btnIniciar.alpha = 1f; btnIniciar.text = "Tentar novamente"
                return@launch
            }
            val array = json.getJSONArray("materias")
            val materias = (0 until array.length()).map { i ->
                val nome = array.getJSONObject(i).getString("nome")
                MateriaItem(nome, MateriaVisual.sigla(nome), array.getJSONObject(i).optInt("total"), array.getJSONObject(i).optInt("concluidos"))
            }
            tvCarregando.text = if (materias.none { it.capitulos > 0 }) "Os livros estão sendo preparados. Volte em breve." else ""
            tvCarregando.visibility = if (materias.none { it.capitulos > 0 }) View.VISIBLE else View.GONE
            lista.adapter = MateriaAdapter(materias) { item ->
                materiaSelecionada = item
                btnIniciar.isEnabled = true
                btnIniciar.alpha = 1f
            }
            btnIniciar.text = "Ver conteúdos"
            materias.firstOrNull { it.nome == materiaRestaurada && it.capitulos > 0 }?.let { item ->
                materiaSelecionada = item
                (lista.adapter as MateriaAdapter).selecionar(materias.indexOf(item))
                btnIniciar.isEnabled = true; btnIniciar.alpha = 1f
            }
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState); outState.putString("materia", materiaSelecionada?.nome ?: materiaRestaurada)
    }

    private fun abrirConteudos() {
        val materia = materiaSelecionada ?: return
        startActivity(android.content.Intent(this, ConteudosActivity::class.java).apply {
            this@SelecionarMateriaActivity.intent.extras?.let { putExtras(it) }
            putExtra("MATERIA", materia.nome)
        })
    }
}
