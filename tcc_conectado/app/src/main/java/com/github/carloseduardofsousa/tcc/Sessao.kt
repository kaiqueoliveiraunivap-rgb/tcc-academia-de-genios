package com.github.carloseduardofsousa.tcc

import android.content.Context
import android.content.Intent

/**
 * Guarda a sessão do usuário logado (token + dados básicos) em SharedPreferences.
 *
 * O token é a única coisa que o app envia para provar quem ele é. O perfil
 * salvo aqui serve só para decidir o que mostrar na tela: quem realmente
 * decide o que pode ser lido ou alterado é o backend, que confere o token
 * a cada requisição.
 */
object Sessao {

    private const val ARQUIVO      = "sessao_appest"
    private const val KEY_TOKEN    = "token"
    private const val KEY_ID       = "id_usuario"
    private const val KEY_NOME     = "nome"
    private const val KEY_PERFIL   = "tipo_perfil"
    private const val KEY_ID_TURMA = "id_turma"

    private fun prefs(context: Context) =
        context.applicationContext.getSharedPreferences(ARQUIVO, Context.MODE_PRIVATE)

    fun salvar(
        context: Context,
        token: String,
        idUsuario: Int,
        nome: String,
        tipoPerfil: String,
        idTurma: Int
    ) {
        prefs(context).edit()
            .putString(KEY_TOKEN, token)
            .putInt(KEY_ID, idUsuario)
            .putString(KEY_NOME, nome)
            .putString(KEY_PERFIL, tipoPerfil.lowercase())
            .putInt(KEY_ID_TURMA, idTurma)
            .apply()
    }

    fun token(context: Context): String = prefs(context).getString(KEY_TOKEN, "").orEmpty()

    fun idUsuario(context: Context): Int = prefs(context).getInt(KEY_ID, 0)

    fun nome(context: Context): String =
        prefs(context).getString(KEY_NOME, "").orEmpty().ifEmpty { "Professor(a)" }

    fun tipoPerfil(context: Context): String = prefs(context).getString(KEY_PERFIL, "").orEmpty()

    fun idTurma(context: Context): Int = prefs(context).getInt(KEY_ID_TURMA, 0)

    // O Android atende apenas alunos e professores. Sessões de outros perfis
    // salvas por versões anteriores não habilitam as telas do aplicativo.
    fun estaLogado(context: Context): Boolean = token(context).isNotEmpty() &&
        (tipoPerfil(context) == "aluno" || tipoPerfil(context) == "professor")

    fun ehProfessor(context: Context): Boolean = tipoPerfil(context) == "professor"

    fun limpar(context: Context) {
        prefs(context).edit().clear().apply()
    }

    /**
     * Avisa o servidor para invalidar o token, limpa os dados locais e volta
     * para o login zerando a pilha de telas (o botão "voltar" não pode
     * devolver o usuário para dentro do app depois de sair).
     */
    fun encerrar(context: Context) {
        val token = token(context)
        if (token.isNotEmpty()) {
            Thread { ApiClient.post("logout.php", mapOf("token" to token)) }.start()
        }
        limpar(context)

        val intent = Intent(context, LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        context.startActivity(intent)
    }
}
