package com.github.carloseduardofsousa.tcc

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

/**
 * Única porta de entrada do dashboard para o servidor.
 *
 * Concentra aqui a montagem da requisição (incluindo o token da sessão) e a
 * conversão do JSON para os modelos. As Activities não conhecem nomes de
 * campo nem endpoints — se o backend mudar, só este arquivo muda.
 */
class DashboardRepository(private val context: Context) {

    fun carregarVisaoGeral(): ResultadoApi<VisaoGeral> =
        chamar("dashboard_turmas.php", emptyMap()) { json ->
            VisaoGeral(
                mediaAprovacao = json.optDouble("media_aprovacao", 6.0),
                turmas = json.optJSONArray("turmas").paraLista { lerTurmaResumo(it) }
            )
        }

    fun carregarDetalhe(idTurma: Int, filtro: FiltroAnalise): ResultadoApi<TurmaDetalhe> {
        val params = mutableMapOf("id_turma" to idTurma.toString())
        params.putAll(filtro.comoParametros())

        return chamar("dashboard_turma_detalhe.php", params) { json -> lerDetalhe(json) }
    }

    /** Devolve a mensagem de sucesso vinda do servidor. */
    fun editarNota(idNota: Int, valor: String): ResultadoApi<String> =
        chamar(
            "dashboard_editar_nota.php",
            mapOf("id_nota" to idNota.toString(), "valor" to valor)
        ) { json -> json.optString("mensagem", "Nota atualizada.") }

    // ── Infraestrutura ──────────────────────────────────────────────────────

    /**
     * Executa a chamada, injeta o token e transforma o resultado.
     * Roda na thread em que for chamada — quem chama usa Dispatchers.IO.
     */
    private fun <T> chamar(
        endpoint: String,
        params: Map<String, String>,
        converter: (JSONObject) -> T
    ): ResultadoApi<T> {
        val token = Sessao.token(context)
        if (token.isEmpty()) return ResultadoApi.SessaoExpirada

        val resposta = ApiClient.postComStatus(endpoint, params + ("token" to token))

        if (resposta.sessaoExpirada) return ResultadoApi.SessaoExpirada
        if (resposta.codigo == 0) {
            return ResultadoApi.Erro("Sem conexão com o servidor. Verifique sua internet.")
        }

        val corpo = resposta.corpo
        if (corpo.isNullOrBlank()) {
            return ResultadoApi.Erro("O servidor não respondeu.")
        }

        return try {
            val json = JSONObject(corpo)
            if (!resposta.sucesso || json.optString("status") == "erro") {
                ResultadoApi.Erro(json.optString("mensagem", "Não foi possível carregar os dados."))
            } else {
                ResultadoApi.Sucesso(converter(json))
            }
        } catch (e: Exception) {
            ResultadoApi.Erro("Resposta inesperada do servidor.")
        }
    }

    // ── Conversão do JSON ───────────────────────────────────────────────────

    private fun lerTurmaResumo(item: JSONObject) = TurmaResumo(
        idTurma             = item.optInt("id_turma"),
        nomeTurma           = item.optString("nome_turma"),
        anoLetivo           = item.optString("ano_letivo"),
        totalAlunos         = item.optInt("total_alunos"),
        totalAvaliados      = item.optInt("total_avaliados"),
        mediaGeral          = item.optDouble("media_geral", 0.0),
        percentualAprovacao = item.optDouble("percentual_aprovacao", 0.0),
        percentualAbaixo    = item.optDouble("percentual_abaixo", 0.0),
        melhorAluno         = item.optNullableString("melhor_aluno"),
        melhorMedia         = item.optDouble("melhor_media", 0.0),
        menorAluno          = item.optNullableString("menor_aluno"),
        menorMedia          = item.optDouble("menor_media", 0.0),
        variacaoMedia       = item.optDouble("variacao_media", 0.0),
        evolucao            = item.optJSONArray("evolucao").paraLista { lerEvolucao(it) }
    )

    private fun lerEvolucao(item: JSONObject) = PontoEvolucao(
        periodo = item.optString("periodo"),
        rotulo  = item.optString("rotulo"),
        media   = item.optDouble("media", 0.0)
    )

    private fun lerDetalhe(json: JSONObject): TurmaDetalhe {
        val turma   = json.optJSONObject("turma") ?: JSONObject()
        val filtros = json.optJSONObject("filtros") ?: JSONObject()
        val resumo  = json.optJSONObject("resumo") ?: JSONObject()

        return TurmaDetalhe(
            idTurma        = turma.optInt("id_turma"),
            nomeTurma      = turma.optString("nome_turma"),
            anoLetivo      = turma.optString("ano_letivo"),
            totalAlunos    = turma.optInt("total_alunos"),
            mediaAprovacao = json.optDouble("media_aprovacao", 6.0),
            filtros = FiltrosDisponiveis(
                materias = filtros.optJSONArray("materias").paraLista {
                    OpcaoFiltro(it.optInt("id_materia").toString(), it.optString("nome"))
                },
                avaliacoes = filtros.optJSONArray("avaliacoes").paraLista {
                    OpcaoFiltro(it.optInt("id_avaliacao").toString(), it.optString("titulo"))
                },
                periodos = filtros.optJSONArray("periodos").paraLista {
                    OpcaoFiltro(it.optString("periodo"), it.optString("rotulo"))
                }
            ),
            resumo = ResumoTurma(
                totalNotas          = resumo.optInt("total_notas"),
                totalAvaliados      = resumo.optInt("total_avaliados"),
                mediaTurma          = resumo.optDouble("media_turma", 0.0),
                maiorNota           = resumo.optDouble("maior_nota", 0.0),
                menorNota           = resumo.optDouble("menor_nota", 0.0),
                aprovados           = resumo.optInt("aprovados"),
                reprovados          = resumo.optInt("reprovados"),
                percentualAprovacao = resumo.optDouble("percentual_aprovacao", 0.0)
            ),
            distribuicao = json.optJSONArray("distribuicao").paraLista {
                FaixaDistribuicao(
                    rotulo     = it.optString("rotulo"),
                    quantidade = it.optInt("quantidade"),
                    percentual = it.optDouble("percentual", 0.0)
                )
            },
            porAvaliacao = json.optJSONArray("por_avaliacao").paraLista {
                AvaliacaoResumo(
                    idAvaliacao = it.optInt("id_avaliacao"),
                    titulo      = it.optString("titulo"),
                    data        = it.optString("data"),
                    totalNotas  = it.optInt("total_notas"),
                    media       = it.optDouble("media", 0.0),
                    maiorNota   = it.optDouble("maior_nota", 0.0),
                    menorNota   = it.optDouble("menor_nota", 0.0)
                )
            },
            evolucao = json.optJSONArray("evolucao").paraLista { lerEvolucao(it) },
            alunos = json.optJSONArray("alunos").paraLista { aluno ->
                AlunoAnalise(
                    idAluno  = aluno.optInt("id_aluno"),
                    nome     = aluno.optString("nome"),
                    media    = aluno.optDouble("media", 0.0),
                    aprovado = aluno.optBoolean("aprovado"),
                    notas = aluno.optJSONArray("notas").paraLista {
                        NotaLancada(
                            idNota          = it.optInt("id_nota"),
                            idAvaliacao     = it.optInt("id_avaliacao"),
                            tituloAvaliacao = it.optString("titulo"),
                            data            = it.optString("data"),
                            valor           = it.optDouble("valor", 0.0)
                        )
                    }
                )
            }
        )
    }
}

/** Converte um JSONArray (possivelmente nulo) em lista, item a item. */
private fun <T> JSONArray?.paraLista(converter: (JSONObject) -> T): List<T> {
    if (this == null) return emptyList()
    return (0 until length()).mapNotNull { i -> optJSONObject(i)?.let(converter) }
}

/** optString devolve a string "null" quando o campo é null no JSON; aqui vira null de verdade. */
private fun JSONObject.optNullableString(chave: String): String? {
    if (isNull(chave)) return null
    return optString(chave).takeIf { it.isNotBlank() }
}
