# Preparar o projeto em outro PC (Windows + emulador Android)

Este roteiro prepara uma cópia nova para a apresentação. O GitHub contém o código, a estrutura SQL, as migrações, o catálogo dos livros e capturas de tela. O banco preenchido, as senhas e a chave de IA são criados ou configurados **em cada computador**; não acompanham o clone.

## 1. Instalar os programas

- Git e Android Studio com o Android SDK Platform 36.1 e um emulador configurado.
- XAMPP em `C:\xampp`, com PHP 8.2 ou superior e MySQL. Se estiver em outra pasta, use `-Xampp` nos comandos abaixo.
- Para importar PDFs: Python 3 com `pypdf` e `pdftotext` (Poppler) no PATH. Se o Git para Windows tiver `pdftotext` em `C:\Program Files\Git\mingw64\bin`, o script de importação já procura esse caminho.
- Internet para baixar dependências do Android Studio, o livro do Drive e chamar a IA.

Instale a biblioteca do Python com `python -m pip install "pypdf>=5,<7"` e confira com `python -c "import pypdf"`. Confira o extrator com `pdftotext -v`. Se instalar Poppler em outro local, adicione a pasta `bin` ao PATH antes de iniciar o terminal.

## 2. Clonar e preparar a configuração privada

No PowerShell, na pasta onde deseja guardar o projeto:

```powershell
git clone https://github.com/CarlosEduardoFSousa/Aplicativo-para-estudos.git
cd Aplicativo-para-estudos
Copy-Item .\config.example.php .\config.local.php
notepad .\config.local.php
```

O arquivo `config.local.php` fica na raiz. Informe ali `GEMINI_API_KEY` e `GEMINI_MODEL` válidos para a conta de IA que será usada. A chave não pode ser obtida do repositório; peça ao responsável pelo projeto para fornecê-la por meio privado ou use uma chave própria. **Nunca faça commit desse arquivo.** Se o MySQL do XAMPP tiver senha para `root`, preencha também `DB_PASSWORD`. Mantenha `DB_NAME` como `appest` para seguir este roteiro.

## 3. Iniciar API, banco e contas locais

Na raiz do projeto, execute:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\iniciar-local.ps1
```

O script inicia o MySQL e a API, cria o banco `appest` e aplica as tabelas sem exigir importação manual de SQL. Também cria uma conta de demonstração para aluno, professor e coordenação. Cada clone recebe **senhas aleatórias próprias**. Leia os e-mails e senhas no arquivo privado `.runtime/acessos-locais.json`; ele não é enviado ao GitHub. A API deve responder em `http://127.0.0.1:8088/php_appest/saude.php`.

Se o XAMPP estiver em outra pasta, execute `iniciar-local.ps1 -Xampp "CAMINHO_DO_XAMPP"`. Se a porta 3306 ou 8088 já pertencer a outro serviço, consulte a mensagem do script antes de mudar a configuração.

## 4. Preparar ao menos um livro antes da apresentação

O instalador registra o catálogo de PDFs, mas a seleção do aluno só mostra livros com capítulos já extraídos. Para preparar o livro **Matemática básica e vetores** uma vez:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\preparar-livros-local.ps1
```

O comando baixa o PDF do Drive, extrai os capítulos e grava o texto no MySQL. Ele precisa de Python, `pypdf`, `pdftotext` e acesso ao arquivo do Drive. Para preparar os livros de todas as matérias, use `-Todos`; isso consome mais tempo e deve ser feito com antecedência. Se um PDF não estiver acessível, a falha é mostrada sem apagar os demais. Outra opção é enviar um PDF com texto selecionável pelo painel da coordenação.

## 5. Executar o aplicativo e o painel

Abra **a subpasta `tcc_conectado`** no Android Studio, selecione o emulador e clique em **Run**. O primeiro build pode demorar porque o Gradle precisa baixar as dependências; aguarde a sincronização terminar antes da apresentação. O app usa `10.0.2.2` para acessar a API do PC pelo emulador. Entre com a conta `aluno` ou `professor` do arquivo de acessos locais.

Para mostrar o painel da coordenação, execute `iniciar-coordenacao.cmd` na raiz e entre com a conta `admin` do mesmo arquivo. No painel, a escola pode gerenciar usuários, turmas e livros. Veja [COORDENACAO.md](COORDENACAO.md) para CSV e importação de PDFs.

## Conferência rápida

1. Verifique se a API responde em `http://127.0.0.1:8088/php_appest/saude.php`.
2. Entre no app como aluno, escolha **Estudar → Matemática → Matemática básica e vetores → Capítulo 1**. A primeira geração com IA pode levar cerca de um minuto; depois, o estudo válido é reutilizado.
3. Conclua o quiz e abra os gráficos e o ranking. Entre como professor para ver a turma e sua orientação coletiva.
4. Entre no painel como `admin` para ver as contas, a turma e o livro importado.

Faça essa conferência **antes do dia da apresentação**. Se a rede ou a IA estiver indisponível no momento, as capturas em `capturas/` mostram as telas já validadas. O ambiente de desenvolvimento usa o banco do próprio PC; resultados feitos em outro computador não aparecem automaticamente neste clone.
