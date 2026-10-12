# Biblioteca didática

O aluno escolhe matéria, frente e capítulo. Os 30 PDFs do catálogo do Drive foram processados neste banco local: 70 frentes e 337 unidades de estudo. Um clone novo precisa importar os PDFs uma vez; o catálogo no Git não contém o texto dos livros. O número de unidades pode ser maior que o número de capítulos impressos, pois capítulos extensos são divididos em partes para caber integralmente na solicitação à IA. Cada unidade guarda o texto e o número físico das páginas do PDF. A consulta no app lê o banco; ela não baixa nem extrai os PDFs a cada acesso.

## Como preparar os livros

O catálogo inicial está em `biblioteca/catalogo-drive.json`. Para importar todos os PDFs cadastrados, com PHP, Python 3, `pypdf` e `pdftotext` (Poppler) instalados:

```powershell
php php_appest/tools/importar_drive_automatico.php --todos
```

Para um único livro: `php php_appest/tools/importar_drive_automatico.php --id ID_DO_DRIVE`. O comando baixa o PDF para `.runtime/pdfs`, extrai os marcadores de frentes e capítulos, prepara o texto e grava no MySQL. Livros já publicados são ignorados, então o processamento demorado ocorre uma vez. Use `--reprocess` para criar uma nova versão quando o PDF ou o extrator mudar. A versão anterior é desativada sem apagar o histórico. O instalador do app apenas sincroniza os metadados de livros ausentes; não baixa PDFs nem reativa versões retiradas.

O ambiente Docker inclui Python, `pypdf` e Poppler. Em outra instalação, configure `PDF_PYTHON_BIN` se o Python com `pypdf` não estiver no PATH. A importação só pode ser executada por linha de comando no servidor; não existe endpoint de upload público. Guarde os PDFs e os JSONs preparados fora da pasta pública e fora do Git.

PDFs precisam ter texto selecionável e marcadores corretos para identificação automática. Um PDF digitalizado sem OCR, com marcadores ausentes ou texto insuficiente é recusado para revisão manual. Para importar um JSON preparado ou revisado manualmente, use `php php_appest/tools/importar_livro.php caminho/livro.json`. O formato aceita `frentes`, cada uma com seus `capitulos`, `paginas` e `revisado`. Arquivos antigos que possuem `capitulos` diretamente viram uma frente única.

**A extração automática não equivale à revisão pedagógica.** Fórmulas, diagramas, tabelas e símbolos podem perder informação no texto do PDF. Antes de usar o material em avaliação formal, confira capítulos e respostas contra as páginas originais. O painel da coordenação permite enviar PDFs, informar páginas de capítulos quando faltam marcadores, inspecionar os capítulos publicados e desativar edições.

## Resumo e quiz

O servidor envia apenas o texto completo do capítulo selecionado ao Gemini, dividido em trechos identificados, sem pesquisa na internet. A explicação é gerada somente depois de selecionar um capítulo e aborda apenas esse conteúdo. Ela combina pelo menos seis blocos com subtítulos e parágrafos, listas de tópicos em alguns blocos e palavras-chave do capítulo adequadas à matéria ao final, com extensão proporcional à fonte. O quiz tem sempre cinco questões de nível médio e quatro alternativas por questão. As perguntas e o feedback são autossuficientes, sem referências a páginas, ao PDF ou a um texto que não esteja no enunciado. As referências internas são recuperadas diretamente dos trechos originais selecionados pela IA e validadas contra o capítulo. Essa verificação confirma a origem do trecho, mas não garante a correção da interpretação da IA. Se a fonte for insuficiente ou a validação falhar, a API retorna erro.

A orientação do professor vale para todos os alunos matriculados na turma naquela matéria. Ela permanece ativa até ser substituída; o primeiro aluno não a consome. Orientações individuais antigas ficam apenas no histórico. O cache considera o texto do capítulo, o modelo, a versão do formato e a orientação vigente. Estudos personalizados são reutilizados entre alunos da mesma turma, com autorização verificada também ao concluir o quiz. A primeira geração do novo formato pode demorar; as seguintes reutilizam o resultado. Os estudos e resultados anteriores são preservados.

Os parágrafos da explicação do capítulo e o feedback das respostas do quiz usam justificação nativa no Android 8 ou superior. A criação e a atualização das colunas necessárias são feitas pelo instalador automaticamente ao executar o app pelo Android Studio, inclusive quando a API local já está aberta.

Testes: `php php_appest/tests/estudo_formato_test.php` verifica a preservação do capítulo, referências e formato; `php php_appest/tests/estudo_turma_test.php` cria e remove um banco temporário isolado para testar dois alunos da turma, isolamento entre turmas, atualização da orientação e reenvios sem duplicação de resultados. Também verifica paginação do histórico, autorização da revisão, alternativa escolhida, tentativas antigas, progresso por capítulo distinto e separação mensal do ranking. O segundo teste requer permissão local para criar e remover esse banco temporário.

O histórico usa `historico_estudos.php` com cursor `antes_id` e páginas de 20 tentativas. `revisar_estudo.php` exige o token do próprio aluno e o `id_quiz` retornado pela conclusão. A coluna aditiva `resposta_estudo.alternativa_escolhida` é nula em registros antigos. As referências e o JSON original do estudo continuam preservados; livros desativados podem ser revisados pelo dono da tentativa, mas não usados para novos quizzes. As consultas de progresso contam capítulos distintos e exibem apenas os capítulos ativos/revisados do catálogo atual.

Configure `GEMINI_API_KEY` e `GEMINI_MODEL` em `config.local.php`, fora de `php_appest`, ou nas variáveis de ambiente da API. Nunca coloque a chave no Android. A conclusão do quiz é corrigida no servidor e registrada em histórico, ranking e gráficos do professor. A tentativa possui identificador único para evitar pontos duplicados em reenvios.

## Administração do acervo

As tabelas `livro_didatico`, `frente_livro`, `capitulo_livro` e `estudo_gerado` separam catálogo, conteúdo e cache. O painel da coordenação usa essa estrutura para publicar e desativar livros. Para retirar uma edição sem excluir o histórico, use o painel ou, em manutenção por SQL:

```sql
UPDATE livro_didatico SET ativo=0 WHERE id_livro=ID_DA_EDICAO;
```

A listagem do aluno e a da coordenação consultam essas tabelas. A extração demorada ocorre somente quando um PDF novo é enviado ou alterado.

## Verificação

```powershell
php php_appest/tests/estudo_validacao_test.php
cd tcc_conectado
.\gradlew.bat :app:assembleDebug
```
