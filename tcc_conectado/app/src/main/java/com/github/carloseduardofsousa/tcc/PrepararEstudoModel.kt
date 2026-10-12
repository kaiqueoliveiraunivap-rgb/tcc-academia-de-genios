package com.github.carloseduardofsousa.tcc

import androidx.lifecycle.ViewModel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

data class PreparacaoEstudo(val idCapitulo: Int = 0, val titulo: String = "", val carregando: Boolean = false, val resposta: ApiResposta? = null)

/** Uma geração por vez, mantida na rotação da tela; sem guardar a Activity. */
class PrepararEstudoModel : ViewModel() {
    private val escopo = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val atual = MutableStateFlow(PreparacaoEstudo())
    val estado = atual.asStateFlow()
    fun gerar(token: String, idCapitulo: Int, titulo: String, idTurma: Int) {
        if (atual.value.carregando) return
        atual.value = PreparacaoEstudo(idCapitulo, titulo, true)
        escopo.launch {
            val params = mutableMapOf("token" to token, "id_capitulo" to idCapitulo.toString())
            if (idTurma > 0) params["id_turma"] = idTurma.toString()
            val resposta = withContext(Dispatchers.IO) { ApiClient.postComStatus("gerar_estudo.php", params, 115000) }
            atual.value = PreparacaoEstudo(idCapitulo, titulo, false, resposta)
        }
    }
    fun limpar() { if (!atual.value.carregando) atual.value = PreparacaoEstudo() }
    override fun onCleared() { escopo.cancel() }
}
