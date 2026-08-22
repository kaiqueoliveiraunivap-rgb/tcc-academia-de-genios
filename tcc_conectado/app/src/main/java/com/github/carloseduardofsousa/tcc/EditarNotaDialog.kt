package com.github.carloseduardofsousa.tcc

import android.app.Activity
import android.view.LayoutInflater
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout

/**
 * Modal de edição de nota.
 *
 * O professor escolhe qual avaliação daquele aluno quer corrigir e digita o
 * novo valor. A validação daqui é só conveniência para o usuário: quem decide
 * de fato se o valor é aceitável e se o professor pode alterar aquela nota é
 * o dashboard_editar_nota.php, no servidor.
 */
object EditarNotaDialog {

    /**
     * @param aluno aluno cujas notas serão editadas
     * @param idAvaliacaoInicial avaliação já selecionada ao abrir (a do filtro atual)
     * @param aoSalvar chamado com (idNota, valorDigitado); devolve a mensagem de
     *        erro do servidor, ou null se salvou. O diálogo só fecha quando salva.
     */
    fun mostrar(
        activity: Activity,
        aluno: AlunoAnalise,
        idAvaliacaoInicial: Int?,
        aoSalvar: (idNota: Int, valor: String, aoTerminar: (String?) -> Unit) -> Unit
    ) {
        if (aluno.notas.isEmpty()) {
            android.widget.Toast.makeText(
                activity,
                "${aluno.nome} não tem notas lançadas para os filtros escolhidos.",
                android.widget.Toast.LENGTH_LONG
            ).show()
            return
        }

        val view = LayoutInflater.from(activity).inflate(R.layout.dialog_editar_nota, null)
        val txtAluno   = view.findViewById<TextView>(R.id.txtAlunoDialog)
        val container  = view.findViewById<LinearLayout>(R.id.containerAvaliacoesDialog)
        val tilNota    = view.findViewById<TextInputLayout>(R.id.tilNota)
        val etNota     = view.findViewById<TextInputEditText>(R.id.etNota)

        txtAluno.text = "${aluno.nome} · média atual ${Formato.nota(aluno.media)}"

        // Começa na avaliação que o professor já estava olhando na tela.
        var notaSelecionada = aluno.notas.firstOrNull { it.idAvaliacao == idAvaliacaoInicial }
            ?: aluno.notas.first()

        fun carregarNoCampo(nota: NotaLancada) {
            notaSelecionada = nota
            etNota.setText(Formato.notaPrecisa(nota.valor))
            etNota.setSelection(etNota.text?.length ?: 0)
            tilNota.error = null
        }

        ChipFiltro.montar(
            container,
            aluno.notas.map { OpcaoFiltro(it.idNota.toString(), it.tituloAvaliacao) },
            notaSelecionada.idNota.toString()
        ) { idNota ->
            aluno.notas.firstOrNull { it.idNota.toString() == idNota }?.let { carregarNoCampo(it) }
        }

        carregarNoCampo(notaSelecionada)

        val dialog = AlertDialog.Builder(activity)
            .setTitle("Editar nota")
            .setView(view)
            .setPositiveButton("Salvar", null)   // null: o clique é tratado abaixo
            .setNegativeButton("Cancelar", null) // fecha sem salvar nada
            .create()

        dialog.show()

        // Tratado manualmente para o diálogo NÃO fechar quando a validação falha.
        dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
            val digitado = etNota.text?.toString()?.trim().orEmpty()
            val erro = validar(digitado)

            if (erro != null) {
                tilNota.error = erro
                return@setOnClickListener
            }

            tilNota.error = null
            val botao = dialog.getButton(AlertDialog.BUTTON_POSITIVE)
            botao.isEnabled = false

            aoSalvar(notaSelecionada.idNota, digitado) { erroServidor ->
                botao.isEnabled = true
                if (erroServidor == null) {
                    dialog.dismiss()
                } else {
                    tilNota.error = erroServidor
                }
            }
        }
    }

    /** Mesmas regras aplicadas no servidor: obrigatório, numérico e de 0 a 10. */
    private fun validar(valor: String): String? {
        if (valor.isEmpty()) return "Informe a nota."

        val numero = valor.replace(',', '.').toDoubleOrNull()
            ?: return "Use apenas números (ex: 8,5)."

        if (numero < 0.0)  return "A nota não pode ser negativa."
        if (numero > 10.0) return "A nota máxima é 10."

        return null
    }
}
