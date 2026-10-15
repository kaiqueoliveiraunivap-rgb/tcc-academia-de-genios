package com.github.carloseduardofsousa.tcc

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class RegrasApresentacaoTest {
    private fun turma(
        nome: String,
        alunos: Int,
        avaliados: Int,
        media: Double,
        aprovacao: Double
    ) = TurmaResumo(
        idTurma = nome.hashCode(),
        nomeTurma = nome,
        anoLetivo = "2026",
        totalAlunos = alunos,
        totalAvaliados = avaliados,
        mediaGeral = media,
        percentualAprovacao = aprovacao,
        percentualAbaixo = 100.0 - aprovacao,
        melhorAluno = null,
        melhorMedia = 0.0,
        menorAluno = null,
        menorMedia = 0.0,
        variacaoMedia = 0.0,
        evolucao = emptyList()
    )

    @Test
    fun visaoGeralPonderaSomenteAlunosAvaliados() {
        val visao = VisaoGeral(
            mediaAprovacao = 6.0,
            turmas = listOf(
                turma("2º A", 30, 10, 8.0, 80.0),
                turma("2º B", 25, 20, 5.0, 50.0),
                turma("2º C", 18, 0, 0.0, 0.0)
            )
        )

        assertEquals(73, visao.totalAlunos)
        assertEquals(6.0, visao.mediaGeral, 0.001)
        assertEquals(60.0, visao.percentualAprovacaoGeral, 0.001)
    }

    @Test
    fun visaoGeralSemNotasNaoInventaMedia() {
        val semNotas = turma("1º A", 20, 0, 0.0, 0.0)
        val visao = VisaoGeral(6.0, listOf(semNotas))

        assertTrue(semNotas.semNotas)
        assertEquals(0.0, visao.mediaGeral, 0.0)
        assertEquals(0.0, visao.percentualAprovacaoGeral, 0.0)
    }

    @Test
    fun ordenacaoEParametrosSaoPrevisiveis() {
        val turmas = listOf(
            turma("3º B", 12, 12, 7.0, 60.0),
            turma("3º A", 25, 25, 8.5, 84.0)
        )

        assertEquals("3º A", OrdenacaoTurmas.MAIOR_MEDIA.aplicar(turmas).first().nomeTurma)
        assertEquals("3º A", OrdenacaoTurmas.ALFABETICA.aplicar(turmas).first().nomeTurma)
        assertEquals(
            mapOf("id_materia" to "4", "periodo" to "2026-10"),
            FiltroAnalise(idMateria = "4", periodo = "2026-10").comoParametros()
        )
        assertTrue(FiltroAnalise().comoParametros().isEmpty())
    }

    @Test
    fun formatacaoUsaPadraoBrasileiroESiglasEstaveis() {
        assertEquals("8,7", Formato.nota(8.74))
        assertEquals("8,75", Formato.notaPrecisa(8.75))
        assertEquals("62,5%", Formato.percentual(62.5))
        assertEquals("▲ 0,4", Formato.variacao(0.42))
        assertEquals("estável", Formato.variacao(0.01))
        assertEquals("MT", MateriaVisual.sigla("Matemática"))
        assertEquals("AR", MateriaVisual.sigla("Artes"))
    }

    @Test
    fun orientacaoDoProfessorMantemEscopoPedagogico() {
        val texto = PromptProfessorBuilder.montar(
            nomeTurma = "2º Ano A",
            nomeProfessor = "Helena Carvalho",
            materia = "Matemática",
            instrucaoBruta = "  Explique as raízes com exemplos resolvidos.  ",
            livro = "Matemática — Volume 2",
            bimestre = 2
        )

        assertTrue(texto.contains("Turma: 2º Ano A"))
        assertTrue(texto.contains("Bimestre: 2"))
        assertTrue(texto.contains("Explique as raízes com exemplos resolvidos."))
        assertTrue(texto.contains("vale para todos os alunos da turma"))
        assertFalse(texto.contains("  Explique"))
    }
}
