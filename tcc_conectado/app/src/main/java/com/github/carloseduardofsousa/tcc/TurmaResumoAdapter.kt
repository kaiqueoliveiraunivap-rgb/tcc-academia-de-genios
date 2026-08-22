package com.github.carloseduardofsousa.tcc

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView

/**
 * Lista de turmas da visão geral. Recebe a lista já ordenada; trocar a
 * ordenação é só chamar [atualizar] com a nova ordem — nada é recarregado
 * do servidor.
 */
class TurmaResumoAdapter(
    private var itens: List<TurmaResumo>,
    private val aoClicar: (TurmaResumo) -> Unit
) : RecyclerView.Adapter<TurmaResumoAdapter.VH>() {

    class VH(item: View) : RecyclerView.ViewHolder(item) {
        val nome      : TextView = item.findViewById(R.id.txtNomeTurma)
        val info      : TextView = item.findViewById(R.id.txtInfoTurma)
        val media     : TextView = item.findViewById(R.id.txtMediaTurma)
        val variacao  : TextView = item.findViewById(R.id.txtVariacaoTurma)
        val aprovacao : TextView = item.findViewById(R.id.txtAprovacaoTurma)
        val melhor    : TextView = item.findViewById(R.id.txtMelhorAluno)
        val menor     : TextView = item.findViewById(R.id.txtMenorAluno)
        val barraOk   : View     = item.findViewById(R.id.barraAprovados)
        val barraRuim : View     = item.findViewById(R.id.barraAbaixo)
    }

    fun atualizar(novos: List<TurmaResumo>) {
        itens = novos
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_turma_resumo, parent, false)
        return VH(view)
    }

    override fun onBindViewHolder(h: VH, position: Int) {
        val turma = itens[position]

        h.nome.text = "👥  ${turma.nomeTurma}"
        h.info.text = "Ano letivo ${turma.anoLetivo} · " +
                "${turma.totalAlunos} aluno${if (turma.totalAlunos == 1) "" else "s"}"

        if (turma.semNotas) {
            // Turma sem nenhuma nota lançada: mostra o estado vazio em vez de "0,0",
            // que seria lido como se a turma tivesse ido mal.
            h.media.text = "—"
            h.variacao.text = ""
            h.aprovacao.text = "Nenhuma nota lançada ainda."
            h.melhor.text = "—"
            h.menor.text = "—"
            aplicarPesos(h, 0.0, 0.0)
        } else {
            h.media.text = Formato.nota(turma.mediaGeral)
            h.variacao.text = Formato.variacao(turma.variacaoMedia)
            h.aprovacao.text = "${Formato.percentual(turma.percentualAprovacao)} na média · " +
                    "${Formato.percentual(turma.percentualAbaixo)} abaixo"
            h.melhor.text = "${turma.melhorAluno ?: "—"} (${Formato.nota(turma.melhorMedia)})"
            h.menor.text  = "${turma.menorAluno ?: "—"} (${Formato.nota(turma.menorMedia)})"
            aplicarPesos(h, turma.percentualAprovacao, turma.percentualAbaixo)
        }

        h.itemView.setOnClickListener { aoClicar(turma) }
        h.itemView.contentDescription =
            "Turma ${turma.nomeTurma}, média ${Formato.nota(turma.mediaGeral)}, " +
            "${Formato.percentual(turma.percentualAprovacao)} de aprovação"
    }

    /** A barra é dividida proporcionalmente entre aprovados e abaixo da média. */
    private fun aplicarPesos(h: VH, aprovados: Double, abaixo: Double) {
        h.barraOk.layoutParams =
            (h.barraOk.layoutParams as LinearLayout.LayoutParams).apply {
                weight = aprovados.toFloat()
            }
        h.barraRuim.layoutParams =
            (h.barraRuim.layoutParams as LinearLayout.LayoutParams).apply {
                weight = abaixo.toFloat()
            }
    }

    override fun getItemCount() = itens.size
}
