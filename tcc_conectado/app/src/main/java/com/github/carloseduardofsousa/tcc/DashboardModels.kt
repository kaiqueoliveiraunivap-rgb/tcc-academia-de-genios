package com.github.carloseduardofsousa.tcc

/**
 * Modelos do Dashboard de Desempenho.
 *
 * Espelham exatamente o JSON devolvido por dashboard_turmas.php e
 * dashboard_turma_detalhe.php. As telas conversam só com estas classes, então
 * trocar a origem dos dados (outro endpoint, outro banco) não obriga a mexer
 * em nenhuma Activity.
 */

// ── Tela 1: visão geral ─────────────────────────────────────────────────────

data class PontoEvolucao(
    val periodo: String,   // "2026-03"
    val rotulo: String,    // "mar/26"
    val media: Double
)

data class TurmaResumo(
    val idTurma: Int,
    val nomeTurma: String,
    val anoLetivo: String,
    val totalAlunos: Int,
    val totalAvaliados: Int,
    val mediaGeral: Double,
    val percentualAprovacao: Double,
    val percentualAbaixo: Double,
    val melhorAluno: String?,
    val melhorMedia: Double,
    val menorAluno: String?,
    val menorMedia: Double,
    val variacaoMedia: Double,
    val evolucao: List<PontoEvolucao>
) {
    /** true quando a turma ainda não tem nenhuma nota lançada. */
    val semNotas: Boolean get() = totalAvaliados == 0
}

data class VisaoGeral(
    val mediaAprovacao: Double,
    val turmas: List<TurmaResumo>
) {
    val totalAlunos: Int get() = turmas.sumOf { it.totalAlunos }

    /** Média da rede, ponderada pela quantidade de alunos avaliados de cada turma. */
    val mediaGeral: Double
        get() {
            val avaliados = turmas.sumOf { it.totalAvaliados }
            if (avaliados == 0) return 0.0
            return turmas.sumOf { it.mediaGeral * it.totalAvaliados } / avaliados
        }

    val percentualAprovacaoGeral: Double
        get() {
            val avaliados = turmas.sumOf { it.totalAvaliados }
            if (avaliados == 0) return 0.0
            val aprovados = turmas.sumOf { it.percentualAprovacao / 100.0 * it.totalAvaliados }
            return aprovados / avaliados * 100.0
        }
}

/**
 * Opções de ordenação da visão geral. A ordenação é feita em memória, sobre a
 * lista já carregada, para a tela responder na hora sem nova chamada de rede.
 */
enum class OrdenacaoTurmas(val rotulo: String) {
    MAIOR_MEDIA("Maior média"),
    MENOR_MEDIA("Menor média"),
    MAIOR_APROVACAO("Maior aprovação"),
    MENOR_APROVACAO("Menor aprovação"),
    MAIS_ALUNOS("Mais alunos"),
    MENOS_ALUNOS("Menos alunos"),
    ALFABETICA("Ordem alfabética");

    fun aplicar(turmas: List<TurmaResumo>): List<TurmaResumo> = when (this) {
        MAIOR_MEDIA     -> turmas.sortedByDescending { it.mediaGeral }
        MENOR_MEDIA     -> turmas.sortedBy { it.mediaGeral }
        MAIOR_APROVACAO -> turmas.sortedByDescending { it.percentualAprovacao }
        MENOR_APROVACAO -> turmas.sortedBy { it.percentualAprovacao }
        MAIS_ALUNOS     -> turmas.sortedByDescending { it.totalAlunos }
        MENOS_ALUNOS    -> turmas.sortedBy { it.totalAlunos }
        ALFABETICA      -> turmas.sortedBy { it.nomeTurma.lowercase() }
    }
}

// ── Tela 2: análise detalhada ───────────────────────────────────────────────

data class NotaLancada(
    val idNota: Int,
    val idAvaliacao: Int,
    val tituloAvaliacao: String,
    val data: String,
    val valor: Double
)

data class AlunoAnalise(
    val idAluno: Int,
    val nome: String,
    val media: Double,
    val aprovado: Boolean,
    val notas: List<NotaLancada>
)

data class FaixaDistribuicao(
    val rotulo: String,      // "6 a 8"
    val quantidade: Int,
    val percentual: Double
)

data class AvaliacaoResumo(
    val idAvaliacao: Int,
    val titulo: String,
    val data: String,
    val totalNotas: Int,
    val media: Double,
    val maiorNota: Double,
    val menorNota: Double
)

data class OpcaoFiltro(val id: String, val rotulo: String)

data class FiltrosDisponiveis(
    val materias: List<OpcaoFiltro>,
    val avaliacoes: List<OpcaoFiltro>,
    val periodos: List<OpcaoFiltro>
)

/** Filtros escolhidos pelo professor. Vazio = "todos". */
data class FiltroAnalise(
    val idMateria: String = "",
    val idAvaliacao: String = "",
    val periodo: String = ""
) {
    fun comoParametros(): Map<String, String> = buildMap {
        if (idMateria.isNotEmpty())   put("id_materia", idMateria)
        if (idAvaliacao.isNotEmpty()) put("id_avaliacao", idAvaliacao)
        if (periodo.isNotEmpty())     put("periodo", periodo)
    }
}

data class ResumoTurma(
    val totalNotas: Int,
    val totalAvaliados: Int,
    val mediaTurma: Double,
    val maiorNota: Double,
    val menorNota: Double,
    val aprovados: Int,
    val reprovados: Int,
    val percentualAprovacao: Double
)

data class TurmaDetalhe(
    val idTurma: Int,
    val nomeTurma: String,
    val anoLetivo: String,
    val totalAlunos: Int,
    val mediaAprovacao: Double,
    val filtros: FiltrosDisponiveis,
    val resumo: ResumoTurma,
    val distribuicao: List<FaixaDistribuicao>,
    val porAvaliacao: List<AvaliacaoResumo>,
    val evolucao: List<PontoEvolucao>,
    val alunos: List<AlunoAnalise>
) {
    val semDados: Boolean get() = resumo.totalNotas == 0
}

// ── Resultado das chamadas ──────────────────────────────────────────────────

/**
 * Resultado de uma chamada ao backend. [SessaoExpirada] é separado de propósito:
 * é o único caso em que a tela precisa devolver o usuário para o login.
 */
sealed class ResultadoApi<out T> {
    data class Sucesso<T>(val dados: T) : ResultadoApi<T>()
    data class Erro(val mensagem: String) : ResultadoApi<Nothing>()
    object SessaoExpirada : ResultadoApi<Nothing>()
}
