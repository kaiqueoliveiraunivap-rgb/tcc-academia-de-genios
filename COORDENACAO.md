# Painel da coordenação

## Abrir no computador

Execute `iniciar-coordenacao.cmd` na raiz do projeto. Ele inicia os mesmos serviços usados pelo app, atualiza a estrutura do banco sem substituir os dados existentes e abre:

http://127.0.0.1:8088/php_appest/coordenacao/

No desenvolvimento, use a conta de perfil `admin` registrada no arquivo privado `.runtime/acessos-locais.json`. Esse arquivo não deve ser enviado ao GitHub nem distribuído à escola. O painel também aceita contas administrativas já existentes no banco, com a senha correspondente.

Para criar uma conta inicial escolhendo nome, e-mail e senha, execute no PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\cadastrar-coordenacao.ps1
```

As próximas contas podem ser criadas em **Equipe da secretaria**. Não existe cadastro administrativo público. Cada pessoa entra com seu próprio e-mail e senha. Gerenciar a equipe exige confirmar a senha da pessoa que está operando o painel.

## Uso

1. Cadastre os **professores** e crie as **turmas**, informando a série e a letra (A, B, C etc.) em campos separados, além do ano e professor responsável. O nome visto no app é montado como `1ª série A`.
2. Cadastre os **alunos**, marcando suas turmas. O login e os vínculos já ficam disponíveis para o aplicativo.
3. Use **Editar** para atualizar dados ou definir uma nova senha. Senha em branco durante a edição mantém a anterior; não apaga a senha. A alteração encerra as sessões abertas do usuário.
4. **Excluir acesso** é uma exclusão lógica: bloqueia o login e remove o cadastro das listagens ativas, preservando o histórico escolar. O e-mail continua reservado. Professores com turmas devem ter as turmas transferidas antes da exclusão. A própria conta não pode ser excluída por ela mesma.

Senhas são armazenadas com `password_hash` e verificadas com `password_verify`. Não são recuperáveis nem exibidas no painel. Não há ação para excluir uma senha separadamente da conta. As contas excluídas permanecem arquivadas com seu hash e sem acesso.

## Importar CSV

Nas seções Alunos ou Professores, escolha **Importar CSV** e baixe o modelo. Use CSV UTF-8, delimitado por `;` ou `,`, com no máximo 500 usuários e 2 MB:

```csv
nome;email;senha;turmas
```

Preencha uma linha por pessoa com a senha escolhida pela escola. `turmas` é opcional para alunos; use os IDs exibidos na seção Turmas, separados por `|` quando houver mais de uma. Professores são vinculados às turmas na seção Turmas.

A primeira etapa apenas valida e mostra uma prévia sem senhas. A segunda confirma a importação. Qualquer linha inválida ou e-mail duplicado impede todo o lote. O CSV não é guardado no servidor; proteja e descarte a cópia que contém as senhas conforme o procedimento da escola.

## Adicionar livros

Na Biblioteca, informe título, matéria e envie o PDF (até 128 MB). Use o mesmo nome da matéria dos livros existentes. O envio entra em uma fila; o processamento acontece fora da requisição, uma vez por PDF. O app consulta o conteúdo já extraído do banco.

- PDFs com texto selecionável e marcadores de capítulos são preparados automaticamente, incluindo frentes e capítulos.
- Sem marcadores, o livro fica em **Revisão necessária**. Informe os intervalos usando as páginas físicas do PDF, contando a capa como página 1. Inclua somente as páginas do capítulo, excluindo gabaritos.
- PDFs digitalizados sem texto precisam de OCR antes do envio. O painel não inventa capítulos nem publica texto insuficiente.
- Desativar um livro o retira das novas seleções sem apagar resultados anteriores.
- O original fica fora da pasta pública. O botão do app usa um link com identificador aleatório de 256 bits; quem receber esse link poderá abrir o PDF enquanto o livro estiver ativo.

O extrator precisa de Python com `pypdf` e Poppler (`pdftotext`). O ambiente atual já possui essas dependências. Em outra máquina, configure `PDF_PYTHON_BIN`; o Dockerfile já instala os componentes.

## Banco, serviços e implantação

As novas tabelas e a coluna `usuario.excluido_em` são instaladas automaticamente por `php_appest/tools/instalar.php`. A base continua sendo `appest`, com as migrações sobre o SQL do projeto.

Uploads e arquivos temporários ficam em `.runtime/coordenacao`, ignorada pelo Git. Faça backup dessa pasta e do MySQL juntos. Em hospedagem, configure `COORD_STORAGE_DIR` para um volume persistente privado, fora do diretório público. Use HTTPS; em `APP_ENV=production`, o cookie de acesso exige conexão segura.

O worker é iniciado pelo painel após o upload. Se necessário, `PHP_CLI_BIN` permite informar o caminho completo do PHP de linha de comando. Se a hospedagem proibir subprocessos, defina `COORD_WORKER_EXTERNO=1` e execute periodicamente `php php_appest/tools/coordenacao_worker.php` com as mesmas variáveis de ambiente. O lock no banco impede dois workers simultâneos. A biblioteca consulta o andamento a cada cinco segundos enquanto há processamento pendente e tenta retomar a fila se o processo tiver sido interrompido. Consulte os logs privados de cada importação quando houver erro de dependência ou PDF.

O servidor local é destinado ao desenvolvimento e à demonstração neste computador/emulador. A implantação para os dispositivos da escola exige endereço acessível pela rede, HTTPS, backup e configuração da URL da API no app, conforme `HOSPEDAGEM.md`.

## Verificações

```powershell
C:\xampp\php\php.exe php_appest\tests\estudo_validacao_test.php
C:\xampp\php\php.exe php_appest\tests\estudo_formato_test.php
C:\xampp\php\php.exe php_appest\tests\estudo_turma_test.php
C:\xampp\php\php.exe php_appest\tests\coordenacao_http_test.php
C:\xampp\php\php.exe php_appest\tests\coordenacao_http_test.php --auto-worker
```

Os testes de integração criam bancos temporários próprios e os removem ao terminar. O teste HTTP usa a porta 8097, não altera o banco do app e cobre autenticação, permissões, CSRF, senhas, CRUD, CSV, matrícula, publicação de PDF e bloqueio de tentativas repetidas. A opção `--auto-worker` também verifica a inicialização em segundo plano e as consultas de recuperação usadas pelo painel.
