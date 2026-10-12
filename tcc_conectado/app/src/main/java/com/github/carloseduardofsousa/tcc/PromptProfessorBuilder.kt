package com.github.carloseduardofsousa.tcc

/** Prévia da orientação que será aplicada a todos os alunos da turma. */
object PromptProfessorBuilder {
    fun montar(nomeTurma: String, nomeProfessor: String, materia: String, instrucaoBruta: String): String =
        buildString {
            appendLine("Turma: $nomeTurma")
            appendLine("Matéria: $materia")
            appendLine("Professor(a): $nomeProfessor")
            appendLine()
            appendLine(instrucaoBruta.trim())
            appendLine()
            append("Esta orientação vale para todos os alunos da turma, nos estudos desta matéria. ")
            append("O conteúdo continuará baseado somente no capítulo do livro, com questões de nível médio. ")
            append("A orientação permanece ativa até ser substituída.")
        }
}
