package com.github.carloseduardofsousa.tcc

import android.util.Log
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URLEncoder
import java.net.URL

/** Resposta crua do servidor: código HTTP + corpo. */
data class ApiResposta(val codigo: Int, val corpo: String?) {
    val sucesso: Boolean get() = codigo in 200..299
    /** 401 = token ausente/expirado; o app deve mandar o usuário para o login. */
    val sessaoExpirada: Boolean get() = codigo == 401
}

object ApiClient {

    /**
     * Faz uma requisição POST para [endpoint] com os [params] fornecidos.
     * Retorna o corpo da resposta como String, ou null em caso de erro.
     */
    fun post(endpoint: String, params: Map<String, String>): String? {
        val resposta = postComStatus(endpoint, params)
        return if (resposta.sucesso) resposta.corpo else null
    }

    /**
     * Igual ao [post], mas devolve também o código HTTP e o corpo das
     * respostas de erro. É o que permite diferenciar "sem internet" de
     * "sessão expirada" ou "sem permissão", em vez de mostrar sempre a
     * mesma mensagem genérica de falha de conexão.
     */
    fun postComStatus(endpoint: String, params: Map<String, String>, timeoutMs: Int = 8000): ApiResposta {
        var conexao: HttpURLConnection? = null
        return try {
            val url = URL(ApiConfig.BASE_URL + endpoint)
            val conn = (url.openConnection() as HttpURLConnection).also { conexao = it }
            conn.requestMethod = "POST"
            conn.doOutput = true
            conn.connectTimeout = 8000
            conn.readTimeout = timeoutMs
            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
            conn.setRequestProperty("Accept", "application/json")

            val body = params.entries.joinToString("&") { (k, v) ->
                "${URLEncoder.encode(k, "UTF-8")}=${URLEncoder.encode(v, "UTF-8")}"
            }

            OutputStreamWriter(conn.outputStream, Charsets.UTF_8).use { it.write(body) }

            val codigo = conn.responseCode
            val stream = if (codigo in 200..299) conn.inputStream else conn.errorStream

            val corpo = stream?.let {
                BufferedReader(InputStreamReader(it, "UTF-8")).use { leitor -> leitor.readText() }
            }

            ApiResposta(codigo, corpo)
        } catch (e: Exception) {
            // Sem corpo ou dados de sessão no log; a tela decide a mensagem ao usuário.
            Log.w("ApiClient", "Falha de rede em $endpoint: ${e.javaClass.simpleName}")
            ApiResposta(0, null)
        } finally {
            conexao?.disconnect()
        }
    }
}
