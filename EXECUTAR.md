# Executar no emulador

Abra **a pasta `tcc_conectado` desta cópia do projeto** no Android Studio, selecione um emulador e clique em **Run**. No Windows, o build de desenvolvimento inicia a API e o MySQL locais automaticamente. Se a API já estiver pronta, a checagem rápida não repete o instalador nem a importação dos livros.

O script `iniciar-local.ps1`:

1. Reutiliza o MySQL do XAMPP em `C:\xampp` ou inicia-o se estiver parado.
2. Executa o instalador idempotente: cria tabelas ausentes e preserva os usuários atuais, incluindo as contas administrativas destinadas ao painel web.
3. Inicia a API PHP diretamente nesta pasta em `127.0.0.1:8088`. Não precisa copiar arquivos para `htdocs` nem ligar o Apache.
4. O emulador acessa essa API por `http://10.0.2.2:8088/php_appest/`.

Se você fechar o servidor e abrir apenas o APK já instalado, execute `iniciar-local.ps1` novamente. O APK não executa PHP/MySQL dentro do Android.

## Entrar

Acima do e-mail e senha há as opções **Aluno** e **Professor**. Se o perfil selecionado não corresponder à conta, o servidor recusa o login. A interface administrativa foi removida do Android e será desenvolvida no painel web.

As contas anteriores continuam válidas no banco. O instalador também cria uma conta local por perfil, uma única vez, com senhas aleatórias. Consulte **`.runtime/acessos-locais.json`**, nesta pasta. O arquivo é privado, ignorado pelo Git e não é servido pela API. As contas administrativas entram somente no painel web da coordenação.

- Aluno: botão separado **Criar conta de aluno**.
- Professor: **Criar conta de professor**, com convite emitido pela coordenação.

## IA e livros

SQL, autenticação e servidor ficam automáticos. Para geração real é necessário preencher `GEMINI_API_KEY` e `GEMINI_MODEL` em **`config.local.php`**, que fica fora de `php_appest` e não vai para o Git/APK. Use um modelo habilitado na sua conta e compatível com o endpoint e saída estruturada.

Nesta instalação local, os 30 PDFs do Drive já foram importados: 70 frentes e 337 unidades de estudo estão no banco. Em outro computador, é necessário preparar os livros uma vez conforme `PREPARAR_OUTRO_PC.md`; o clone contém apenas o catálogo e a estrutura do banco. Depois disso, as telas consultam diretamente o banco. A extração automática pode perder fórmulas e diagramas, por isso revise o material antes de usá-lo em avaliação formal.

## Ambiente

- SDK e `local.properties` são configurados pelo Android Studio em cada computador.
- XAMPP esperado em `C:\xampp`, PHP 8 com mysqli, mbstring e cURL.
- O banco `appest` existente é preservado. Em uma máquina nova, o instalador o cria automaticamente.
- O Apache e o conteúdo antigo em `C:\xampp\htdocs\php_appest` não foram alterados. O app usa a API nova na porta 8088.
- Para outra API: configure `API_BASE_URL` no `tcc_conectado/local.properties`.
- O `debug` aceita HTTP para o XAMPP local. O `release` bloqueia tráfego HTTP: para publicar, defina `API_BASE_URL` com HTTPS antes de gerar o APK/AAB.
- O build debug com a URL local prepara a API automaticamente. Para iniciar sem compilar, execute `iniciar-local.ps1` ou rode a tarefa Gradle `prepararAmbienteLocal`.
- Para hospedar a API e o banco sem XAMPP, siga `HOSPEDAGEM.md`.
- Em CI: use `-PskipLocalApi=true` para compilar sem iniciar serviços. O início automático é somente do build debug com a URL local no Windows.
- Para encerrar apenas a API iniciada por este projeto: `parar-api-local.ps1`.

## Verificação

```powershell
.\iniciar-local.ps1
C:\xampp\php\php.exe php_appest\tests\login_http_test.php
C:\xampp\php\php.exe php_appest\tests\estudo_validacao_test.php
```

O teste de login da API verifica os três perfis do banco, seis combinações incorretas, senha inválida, ausência de perfil, autorização da coordenação e aluno com várias matrículas. O Android disponibiliza apenas aluno e professor. O teste usa somente as contas de teste geradas pelo instalador, encerra os tokens e remove a turma temporária ao terminar.

Os testes locais desta cópia estão descritos em `VALIDACAO.md`. Em outra máquina, a chave Gemini deve ser configurada fora da pasta pública; a disponibilidade do provedor depende da rede e da cota da conta.

O arquivo de sessão Android (`sessao_appest.xml`) é excluído do backup em nuvem e da transferência de aparelho. O usuário precisará entrar novamente após restaurar o aplicativo, em vez de recuperar um token de sessão.
