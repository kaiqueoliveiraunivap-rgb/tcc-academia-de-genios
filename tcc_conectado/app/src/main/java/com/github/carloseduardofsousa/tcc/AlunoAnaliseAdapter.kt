package com.github.carloseduardofsousa.tcc

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView

/**
 * Lista de alunos da análise detalhada, com a ação de editar a nota.
 * Recebe a lista já filtrada e ordenada pelo backend.
 */
class AlunoAnaliseAdapter(
    private var itens: List<AlunoAnalise>,
    private val aoEditar: (AlunoAnalise) -> Unit
) : RecyclerView.Adapter<AlunoAnaliseAdapter.VH>() {

    class VH(item: View) : RecyclerView.ViewHolder(item) {
        val nome    : TextView = item.findViewById(R.id.txtNomeAluno)
        val detalhe : TextView = item.findViewById(R.id.txtDetalheAluno)
        val media   : TextView = item.findViewById(R.id.txtMediaAluno)
        val editar  : Button   = item.findViewById(R.id.btnEditarNota)
    }

    fun atualizar(novos: List<AlunoAnalise>) {
        itens = novos
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_aluno_analise, parent, false)
        return VH(view)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val aluno = itens[position]
        val contexto = h.itemView.context

        h.nome.text = aluno.nome
        h.detalhe.text = "${aluno.notas.size} nota${if (aluno.notas.size == 1) "" else "s"} · " +
                if (aluno.aprovado) "na média" else "abaixo da média"

        h.media.text = Formato.nota(aluno.media)
        h.media.setTextColor(
            ContextCompat.getColor(
                contexto,
                if (aluno.aprovado) R.color.OptCerta else R.color.OptErrada
            )
        )

        h.editar.setOnClickListener { aoEditar(aluno) }
    }

    override fun getItemCount() = itens.size
}
