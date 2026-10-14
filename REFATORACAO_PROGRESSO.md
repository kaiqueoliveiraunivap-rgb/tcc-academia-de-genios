# Refatoração do TCC — checkpoint

Branch local: `refatoracao-tcc`. Não houve push. Não executar exclusões em banco real.

## Integrado

- Migração aditiva do catálogo pedagógico, progresso, atividades e ranking; associação manual de capítulos no painel da secretaria.
- Aluno: dashboard real, matérias da série, conteúdos por bimestre, retomada por unidade, estudo direto, atividades e resultados.
- Professor: turmas próprias, conteúdos, orientação específica ou geral, publicação, conclusão das atividades e dificuldades calculadas pelas respostas.
- Menu do professor direciona para turmas, aprendizagem e ranking; carregamento da lista usa ciclo de vida da tela e oferece nova tentativa em falhas.
- Ranking do mês atual recalculado pelas tentativas reais; meses anteriores continuam na tabela histórica. Um conteúdo com várias unidades vale até cinco pontos pela média das melhores notas das unidades.
- PDFs privados com URL temporária vinculada à sessão; autocadastro público continua bloqueado.
- Geração síncrona de estudos publica fases reais no banco (`aguardando`, preparação, geração, validação, finalização, conclusão ou erro). O app consulta o estado, mantém uma chamada por tela e reaproveita a geração concorrente pelo lock/cache.
- Migração ajusta as collations das colunas pedagógicas `serie` e `materia` para as collations legadas de `turma` e `livro_didatico`; evita erro SQL nas junções sem modificar dados das tabelas legadas.
- `assembleDebug` não inicia mais XAMPP nem o instalador. Para preparar o ambiente local explicitamente, usar `-PstartLocalApi=true`; esse caminho pode executar o instalador. Migrações devem ser testadas no banco isolado.

## Verificado

- `php_appest/tests/pedagogico_smoke.php` com `DB_NAME=academia_refatoracao_test`: passou; cobre autorização, CSRF, catálogo, retomada, atividades com duas unidades, quiz idempotente, ranking e PDF.
- `php_appest/tests/gemini_smoke.php` com chave apenas no ambiente e material sintético: passou; estudo validado, cinco questões e cache reutilizado.
- `estudo_validacao_test.php` e `estudo_formato_test.php`: passaram; `painel.js` passou em `node --check`.
- Migração aplicada duas vezes no banco isolado: passou.
- `:app:assembleDebug --offline -PskipLocalApi=true`: passou. APK instalado e `LoginActivity` aberta no emulador.
- `:app:assembleDebug --offline` sem opção de preparação: passou após a proteção do instalador; `prepararAmbienteLocal` não executou.
- Migração da tabela de fases aplicada duas vezes em `academia_refatoracao_test`: passou. Smoke pedagógico com teste de status/autorização passou em servidor local temporário configurado com modelo, sem chave de IA.
- Lint dos PHP modificados e `git diff --check`: passaram.
- Nesta retomada: `estudo_formato_test.php`, `estudo_validacao_test.php`, `estudo_turma_test.php` e `coordenacao_http_test.php` passaram. O teste de turma tinha expectativa antiga (9 pontos após repetições); agora verifica o limite mensal de 5 pontos por conteúdo.
- `login_http_test.php` agora cria contas apenas em banco temporário próprio; passou e confirmou a remoção desse banco. As três contas de `appest` não foram modificadas.
- Painel da secretaria autenticado em 390 px: oito seções abriram sem overflow horizontal ou erro visível. Tema escuro conferido. Cabeçalho mobile e textos da classificação ajustados; formulário de conteúdo abre e mantém scroll até as ações.
- Android autenticado no `TCC_API36_TESTE`: aluno abriu Matemática > 2º bimestre > Função quadrática, recebeu explicação Gemini com páginas de referência, fez quiz de cinco questões, revisou respostas, consultou histórico, ranking, gráficos e dashboard. Professor abriu sua turma e conteúdo, viu os resultados, salvou orientação e publicou atividade.
- Em novo banco isolado no MySQL do D (porta 3311), atividade publicada pela API apareceu no app do aluno, gerou estudo, permitiu quiz de cinco questões e passou a constar como concluída (3/5). Banco confirmou um vínculo `atividade_quiz` e três pontos mensais, sem duplicação. O MySQL legado em C não pôde ser reiniciado nesta retomada; nenhum dado de `appest` foi alterado.
- `:app:assembleRelease` passou após baixar a dependência de lint; gerou APK unsigned de 4,85 MiB (debug: 6,98 MiB). Ainda não há URL HTTPS de produção nem assinatura para distribuição.
- `gemini_smoke.php` passou no banco isolado do D: geração, cinco questões, cache e nova verificação de fase final `concluido`.
- `pedagogico_smoke.php` passou novamente no banco isolado do D após a correção de collation. O teste agora usa séries exclusivas para conviver com o fixture do emulador.
- `:app:assembleDebug :app:assembleRelease --offline` e recompilação final de `assembleDebug` passaram; APK debug reinstalado no `TCC_API36_TESTE`. PHP lint dos arquivos alterados, `node --check` e `git diff --check` passaram.
- Componentes compartilhados de histórico/revisão agora usam recursos de cor do tema. Histórico conferido visualmente em claro e escuro no emulador; modo escuro restaurado e dashboard deixado aberto.
- Tela de conteúdo do professor recebeu campo de orientação contornado e ações com hierarquia visual consistente; tema escuro conferido no `TCC_API36_TESTE` após carregamento dos dados reais.

## Próximo trabalho

- Testar as transições da IA em geração real e concorrência sob servidor com múltiplos workers. A geração ainda é síncrona; fila assíncrona depende de infraestrutura persistente na hospedagem.
- Refinar tema claro/escuro e componentes das telas legadas, inclusive relatórios de notas, sem misturar notas com quizzes.
- A API PHP embutida de desenvolvimento atende uma requisição por vez: as fases persistem no banco, mas o polling do app pode só receber a fase final. Validar feedback intermediário também sob servidor com múltiplos workers.
- Ampliar testes Android e navegação autenticada de aluno/professor/secretaria; a conclusão da atividade foi testada no aluno, mas falta revisão visual abrangente das telas legadas.
- Revisar desempenho do ranking mensal com volume de dados maior e paginar se necessário.
