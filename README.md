# Academia de Gênios

Aplicativo Android e API PHP/MySQL para estudo por capítulos de livros didáticos. O aluno escolhe matéria, frente e capítulo, lê uma explicação detalhada com subtítulos e responde a um quiz de nível médio. Seus resultados alimentam o histórico, o ranking e os gráficos. O professor acompanha as turmas e personaliza o estudo de todos os alunos de uma turma por matéria.

O aplicativo Android atende alunos e professores. A secretaria/coordenação usa um painel web separado, conectado à mesma API e ao mesmo banco, para administrar usuários, turmas e livros.

## Executar no emulador

Abra `tcc_conectado` no Android Studio e clique em Run com um emulador selecionado. No Windows, o build debug inicia o MySQL e a API PHP locais automaticamente; o emulador acessa a API por `10.0.2.2`. Se quiser iniciar a API sem compilar, execute `./iniciar-local.ps1`. Veja [EXECUTAR.md](EXECUTAR.md) para login, contas locais e detalhes do ambiente.

**Em outro computador**, siga primeiro [PREPARAR_OUTRO_PC.md](PREPARAR_OUTRO_PC.md). O clone contém código, estrutura do banco e catálogo de livros; cada máquina cria seu próprio banco e suas próprias contas de demonstração. A chave da IA fica em `config.local.php` e precisa ser configurada separadamente.

Os serviços locais iniciam em processos separados, com saídas em `.runtime`, para não manter os canais da compilação abertos. Isso permite ao Gradle finalizar mesmo quando MySQL e PHP continuam funcionando.

A chave Gemini e o modelo ficam em `config.local.php`, fora da pasta pública `php_appest`, ou em variáveis de ambiente do servidor. Nunca coloque a chave no código Android ou em `local.properties`.

## Desempenho e uso da IA

O estudo gerado é compartilhado por capítulo, versão do conteúdo, modelo, dificuldade e orientação vigente da turma. Assim, uma nova chamada à Gemini só acontece quando essa combinação ainda não existe; alunos que abrirem o mesmo capítulo aguardam o mesmo lock do banco e reutilizam o resultado validado. Alterar de fato a orientação cria uma nova versão; reenviar a mesma orientação mantém o cache.

O texto do livro continua sendo normalizado e enviado integralmente em trechos rastreáveis, sem cortar conteúdo de forma silenciosa. O prompt e a resposta estruturada foram compactados, e uma nova tentativa reenvia somente a fonte original e o motivo curto da validação — nunca a resposta inválida inteira. Nos logs do servidor são registrados apenas metadados seguros de desenvolvimento (origem cache/nova, duração, caracteres, modelo, retry e uso retornado pela API), sem chave, token ou conteúdo do aluno.

O APK debug aceita HTTP para o XAMPP em `10.0.2.2`; o APK release bloqueia HTTP. Antes de gerar uma release, configure `API_BASE_URL` com uma URL HTTPS de produção.

## Acompanhamento do aluno

O menu inclui **Meu histórico**, com data, capítulo e percentual de cada tentativa. A revisão mostra as cinco questões, a resposta escolhida, a correta e a explicação. Também permite estudar novamente o mesmo capítulo; o conteúdo segue a orientação atual da turma e aproveita o cache válido. Tentativas antigas continuam disponíveis, mas a alternativa escolhida só aparece quando foi registrada — o app não tenta adivinhá-la.

Frentes e capítulos mostram o progresso: um capítulo fica concluído depois de salvar seu quiz, independentemente da nota. Repetir o quiz cria uma nova tentativa no histórico, preserva o melhor resultado exibido e não aumenta a quantidade de capítulos concluídos. O ranking continua mensal, com navegação entre os meses.

O histórico carrega 20 tentativas por página e recicla os cartões na tela. A API usa índices e consultas agregadas para progresso, sem carregar textos de livros nem chamar a IA. A revisão carrega apenas as questões da tentativa escolhida. A geração do capítulo continua durante a rotação da tela; falhas oferecem nova tentativa mantendo a seleção. As migrações são aditivas e aplicadas automaticamente no início local.

## Livros

Nesta instalação local, os 30 PDFs do catálogo do Drive foram preparados no banco, com 70 frentes e 337 unidades de estudo. Esses textos não fazem parte do repositório: em um clone novo, importe os PDFs uma vez com `preparar-livros-local.ps1` ou pelo painel da coordenação. Depois disso, o aluno consulta o MySQL e não espera a extração do PDF a cada acesso. Leia [BIBLIOTECA.md](BIBLIOTECA.md) para detalhes.

## Estrutura

```text
tcc_conectado/   Aplicativo Android (Kotlin/Gradle)
php_appest/      API PHP, migrações e importadores
biblioteca/      Catálogo dos PDFs do Drive
```

A API pode ser hospedada separadamente do Android. A hospedagem será configurada depois da conclusão do TCC; o roteiro atual está em [HOSPEDAGEM.md](HOSPEDAGEM.md).
