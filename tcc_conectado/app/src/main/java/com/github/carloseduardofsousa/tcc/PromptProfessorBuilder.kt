package com.github.carloseduardofsousa.tcc

/**
 * Transforma a orientação em linguagem livre que o professor escreve
 * ("focar em frações", "o aluno troca sujeito e objeto"...) num prompt
 * estruturado, no formato que a Gemini interpreta melhor.
 *
 * O texto gerado aqui é o que o professor revisa na tela de pré-visualização
 * e o que fica salvo em prompt_professor.prompt_final para ser aplicado no
 * próximo quiz do aluno (ver GeminiService.gerarQuestoes).
 */
object PromptProfessorBuilder {

    fun montar(
        nomeAluno: String,
        nomeProfessor: String,
        materia: String,
        dificuldade: String,
        instrucaoBruta: String,
        pontosTotal: Int
    ): String {
        val nivel = when (dificuldade.uppercase()) {
            "FACIL"   -> "fácil, para reforçar o básico"
            "DIFICIL" -> "difícil, para desafiar o aluno"
            else      -> "médio"
        }

        val contextoDesempenho = when {
            pontosTotal == 0  -> "o aluno ainda não pontuou nos quizzes desta turma, então cuidado para não desmotivá-lo."
            pontosTotal < 20  -> "o aluno tem $pontosTotal pontos acumulados até agora — está no começo, prefira exemplos guiados."
            pontosTotal < 60  -> "o aluno tem $pontosTotal pontos acumulados — já domina o básico, pode variar mais os exemplos."
            else               -> "o aluno tem $pontosTotal pontos acumulados — está indo muito bem, pode elevar o desafio dentro do nível pedido."
        }

        return buildString {
            appendLine("Você é um tutor particular de $materia criando questões personalizadas para o aluno $nomeAluno.")
            appendLine("Nível de dificuldade: $nivel.")
            appendLine("Contexto de desempenho: $contextoDesempenho")
            appendLine("Orientação do professor $nomeProfessor sobre o que priorizar: \"${instrucaoBruta.trim()}\"")
            append("Use essa orientação para escolher os exemplos, o vocabulário e o foco de cada questão, ")
            append("mantendo linguagem clara, em português do Brasil e adequada ao nível informado.")
        }
    }
}
