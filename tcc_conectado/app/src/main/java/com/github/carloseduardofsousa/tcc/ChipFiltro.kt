package com.github.carloseduardofsousa.tcc

import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.view.Gravity
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import androidx.core.content.ContextCompat

/**
 * Linha de chips selecionáveis, no mesmo estilo já usado em
 * EnviarPromptActivity e SelecionarMateriaActivity (cantos arredondados,
 * fundo cinza claro, selecionado em índigo com texto branco).
 *
 * É usado pela ordenação da Tela 1 e pelos quatro filtros da Tela 2, para as
 * duas telas terem exatamente o mesmo comportamento e aparência.
 */
object ChipFiltro {

    /**
     * Preenche [container] com um chip por opção.
     *
     * @param opcoes lista de (id, rótulo) a exibir
     * @param idSelecionado id que deve começar marcado
     * @param aoSelecionar chamado com o id escolhido; a tela decide o que fazer
     */
    fun montar(
        container: LinearLayout,
        opcoes: List<OpcaoFiltro>,
        idSelecionado: String,
        aoSelecionar: (String) -> Unit
    ) {
        container.removeAllViews()
        val contexto = container.context

        val corFundoNormal = ContextCompat.getColor(contexto, R.color.ChipFundo)
        val corFundoAtivo  = ContextCompat.getColor(contexto, R.color.Destaque)
        val corTextoNormal = ContextCompat.getColor(contexto, R.color.ChipTexto)

        val chips = mutableListOf<TextView>()

        opcoes.forEach { opcao ->
            val chip = TextView(contexto).apply {
                text = opcao.rotulo
                textSize = 13f
                setPadding(28, 18, 28, 18)
                gravity = Gravity.CENTER
                background = GradientDrawable().apply { cornerRadius = 40f }
                layoutParams = ViewGroup.MarginLayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT
                ).apply { setMargins(0, 0, 12, 0) }
                contentDescription = opcao.rotulo
            }

            chip.setOnClickListener {
                chips.forEach { outro ->
                    val ativo = outro === chip
                    outro.pintar(if (ativo) corFundoAtivo else corFundoNormal,
                                 if (ativo) Color.WHITE else corTextoNormal)
                }
                aoSelecionar(opcao.id)
            }

            val ativo = opcao.id == idSelecionado
            chip.pintar(if (ativo) corFundoAtivo else corFundoNormal,
                        if (ativo) Color.WHITE else corTextoNormal)

            chips.add(chip)
            container.addView(chip)
        }
    }

    private fun TextView.pintar(corFundo: Int, corTexto: Int) {
        (background as GradientDrawable).setColor(corFundo)
        setTextColor(corTexto)
    }
}
