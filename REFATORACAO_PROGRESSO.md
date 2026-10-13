# Refatoração do TCC — checkpoint

Branch local: `refatoracao-tcc`. Não houve push. Não executar exclusões em banco real.

## Integrado

- Migração aditiva do catálogo pedagógico, progresso, atividades e ranking; associação manual de capítulos no painel da secretaria.
- Aluno: dashboard real, matérias da série, conteúdos por bimestre, retomada por unidade, estudo direto, atividades e resultados.
- Professor: turmas próprias, conteúdos, orientação específica ou geral, publicação, conclusão das atividades e dificuldades calculadas pelas respostas.
- Ranking do mês atual recalculado pelas tentativas reais; meses anteriores continuam na tabela histórica. Um conteúdo com várias unidades vale até cinco pontos pela média das melhores notas das unidades.
- PDFs privados com URL temporária vinculada à sessão; autocadastro público continua bloqueado.

## Verificado

- `php_appest/tests/pedagogico_smoke.php` com `DB_NAME=academia_refatoracao_test`: passou; cobre autorização, CSRF, catálogo, retomada, atividades com duas unidades, quiz idempotente, ranking e PDF.
- Migração aplicada duas vezes no banco isolado: passou.
- `:app:assembleDebug --offline -PskipLocalApi=true`: passou. APK instalado e `LoginActivity` aberta no emulador.
- Lint dos PHP modificados e `git diff --check`: passaram.

## Próximo trabalho

- Validar geração real do Gemini em ambiente de teste com credenciais disponíveis apenas por variável de ambiente; nenhuma credencial deve entrar no Git.
- Completar estados reais de preparação da IA; geração assíncrona exige infraestrutura persistente de fila/worker na hospedagem.
- Refinar tema claro/escuro e componentes das telas legadas, inclusive relatórios de notas, sem misturar notas com quizzes.
- Ampliar testes Android e navegação autenticada de aluno/professor/secretaria; executar revisão visual no emulador e painel.
- Revisar desempenho do ranking mensal com volume de dados maior e paginar se necessário.
