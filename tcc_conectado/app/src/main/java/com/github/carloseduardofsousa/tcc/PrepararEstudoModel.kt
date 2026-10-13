package com.github.carloseduardofsousa.tcc

import androidx.lifecycle.ViewModel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

data class PreparacaoEstudo(val idCapitulo: Int = 0, val titulo: String = "", val carregando: Boolean = false, val resposta: ApiResposta? = null, val fase: String = "aguardando")

/** Uma geração por vez, mantida na rotação da tela; sem guardar a Activity. */
class PrepararEstudoModel : ViewModel() {
    private val escopo = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val atual = MutableStateFlow(PreparacaoEstudo())
    val estado = atual.asStateFlow()
    fun gerar(token: String, idCapitulo: Int, titulo: String, idTurma: Int, idConteudo: Int = 0) {
        if (atual.value.carregando) return
        atual.value = PreparacaoEstudo(idCapitulo, titulo, true)
        escopo.launch {
            val params = mutableMapOf("token" to token, "id_capitulo" to idCapitulo.toString())
            if (idTurma > 0) params["id_turma"] = idTurma.toString()
            if (idConteudo > 0) params["id_conteudo"] = idConteudo.toString()
            val acompanhamento = launch {
                while (true) {
                    delay(1400)
                    val status = withContext(Dispatchers.IO) {
                        ApiClient.postComStatus("gerar_estudo.php", params + ("somente_status" to "1"), 8000)
                    }
                    if (status.sucesso) {
                        val fase = runCatching { JSONObject(status.corpo.orEmpty()).optString("fase") }.getOrNull()
                        if (fase in setOf("aguardando", "preparando_material", "gerando_conteudo", "validando_resposta", "finalizando", "concluido", "erro")) {
                            if (atual.value.fase != fase) atual.value = atual.value.copy(fase = fase!!)
                        }
                    }
                }
            }
            try {
                var resposta = withContext(Dispatchers.IO) { ApiClient.postComStatus("gerar_estudo.php", params, 215000) }
                if (resposta.codigo == 202) {
                    acompanhamento.cancel()
                    // Outra chamada detém o lock. Aguardar o resultado compartilhado,
                    // sem disparar uma segunda geração no provedor.
                    for (tentativa in 0 until 120) {
                        delay(1500)
                        val status = withContext(Dispatchers.IO) {
                            ApiClient.postComStatus("gerar_estudo.php", params + ("somente_status" to "1"), 8000)
                        }
                        val fase = runCatching { JSONObject(status.corpo.orEmpty()).optString("fase") }.getOrNull()
                        if (fase != null && atual.value.fase != fase) atual.value = atual.value.copy(fase = fase)
                        if (fase == "concluido") {
                            resposta = withContext(Dispatchers.IO) { ApiClient.postComStatus("gerar_estudo.php", params, 15000) }
                            break
                        }
                        if (fase == "erro") {
                            resposta = ApiResposta(503, null)
                            break
                        }
                    }
                    if (resposta.codigo == 202) resposta = ApiResposta(503, null)
                }
                atual.value = PreparacaoEstudo(idCapitulo, titulo, false, resposta,
                    if (resposta.sucesso) "concluido" else "erro")
            } finally {
                acompanhamento.cancel()
            }
        }
    }
    fun limpar() { if (!atual.value.carregando) atual.value = PreparacaoEstudo() }
    override fun onCleared() { escopo.cancel() }
}
