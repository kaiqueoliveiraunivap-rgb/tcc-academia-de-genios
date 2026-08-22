package com.github.carloseduardofsousa.tcc

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

data class Alternativa(
    val texto: String,
    val ehCorreta: Boolean
)

data class QuestaoGerada(
    val enunciado: String,
    val dificuldade: String,
    val alternativas: List<Alternativa>
)

object GeminiService {

    private const val MODEL = "gemini-2.0-flash"

    // A chave vem do local.properties/variável de ambiente via BuildConfig.
    // Ela não pode ser escrita aqui: o código-fonte é versionado e o APK é
    // distribuível, então uma chave literal vazaria nos dois.
    private val endpoint: String
        get() = "https://generativelanguage.googleapis.com/v1beta/models/$MODEL" +
                ":generateContent?key=${BuildConfig.GEMINI_API_KEY}"

    suspend fun gerarQuestoes(
        materia: String,
        dificuldade: String,
        quantidade: Int = 5,
        instrucaoProfessor: String? = null
    ): Result<List<QuestaoGerada>> = withContext(Dispatchers.IO) {
        try {
            if (BuildConfig.GEMINI_API_KEY.isBlank()) {
                throw IllegalStateException(
                    "Chave da Gemini não configurada. Adicione GEMINI_API_KEY ao local.properties."
                )
            }
            val prompt = buildPrompt(materia, dificuldade, quantidade, instrucaoProfessor)
            val responseJson = callGeminiApi(prompt)
            val questoes = parseQuestoes(responseJson)
            Result.success(questoes)
        } catch (e: Exception) {
            // A mensagem chega até um Toast na tela. Exceções de rede costumam
            // trazer a URL inteira junto — e a URL carrega a chave na query.
            Result.failure(Exception(semChave(e.message ?: e.javaClass.simpleName)))
        }
    }

    private fun semChave(texto: String): String {
        val chave = BuildConfig.GEMINI_API_KEY
        return if (chave.isBlank()) texto else texto.replace(chave, "***")
    }

    private fun buildPrompt(
        materia: String,
        dificuldade: String,
        quantidade: Int,
        instrucaoProfessor: String? = null
    ): String {
        val nivel = when (dificuldade.uppercase()) {
            "FACIL"   -> "fácil, para iniciantes"
            "MEDIO"   -> "médio, para estudantes intermediários"
            "DIFICIL" -> "difícil, para estudantes avançados"
            else      -> "médio"
        }
        val blocoProfessor = if (instrucaoProfessor.isNullOrBlank()) "" else """

            Orientação do professor para este aluno, aplique-a ao gerar as questões:
            "${instrucaoProfessor.trim()}"
        """.trimIndent()
        return """
            Gere $quantidade questões de múltipla escolha sobre: $materia.
            Nível: $nivel.$blocoProfessor
            Responda SOMENTE com JSON válido, sem texto extra:
            {
              "questoes": [
                {
                  "enunciado": "Pergunta aqui?",
                  "dificuldade": "$dificuldade",
                  "alternativas": [
                    {"texto": "Opção A", "ehCorreta": false},
                    {"texto": "Opção B", "ehCorreta": true},
                    {"texto": "Opção C", "ehCorreta": false},
                    {"texto": "Opção D", "ehCorreta": false}
                  ]
                }
              ]
            }
            Regras: 4 alternativas por questão, apenas 1 correta, em português brasileiro.
        """.trimIndent()
    }

    private fun callGeminiApi(prompt: String): String {
        val connection = (URL(endpoint).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            setRequestProperty("Content-Type", "application/json")
            doOutput = true
            connectTimeout = 30000
            readTimeout = 30000
        }
        val body = JSONObject().apply {
            put("contents", JSONArray().put(JSONObject().apply {
                put("parts", JSONArray().put(JSONObject().put("text", prompt)))
            }))
            put("generationConfig", JSONObject().apply {
                put("temperature", 0.7)
                put("maxOutputTokens", 2048)
            })
        }
        connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }

        if (connection.responseCode != HttpURLConnection.HTTP_OK) {
            val erro = connection.errorStream?.bufferedReader()?.readText() ?: "sem detalhes"
            throw Exception("HTTP ${connection.responseCode}: $erro")
        }
        return BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
    }

    private fun parseQuestoes(responseJson: String): List<QuestaoGerada> {
        val text = JSONObject(responseJson)
            .getJSONArray("candidates")
            .getJSONObject(0)
            .getJSONObject("content")
            .getJSONArray("parts")
            .getJSONObject(0)
            .getString("text")
            .replace(Regex("```json\\s*"), "")
            .replace(Regex("```\\s*"), "")
            .trim()

        val arr = JSONObject(text).getJSONArray("questoes")
        return (0 until arr.length()).map { i ->
            val q = arr.getJSONObject(i)
            val alts = q.getJSONArray("alternativas")
            QuestaoGerada(
                enunciado    = q.getString("enunciado"),
                dificuldade  = q.optString("dificuldade", "MEDIO"),
                alternativas = (0 until alts.length()).map { j ->
                    alts.getJSONObject(j).let {
                        Alternativa(it.getString("texto"), it.getBoolean("ehCorreta"))
                    }
                }
            )
        }
    }
}