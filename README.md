# Academia de Gênios — TCC

Sistema de quiz escolar com geração de questões por IA e acompanhamento de
desempenho pelo professor.

O aluno escolhe uma matéria e um nível, o app gera as questões com a API Gemini
e registra o resultado. O professor acompanha as turmas, lança e corrige notas,
e pode escrever uma orientação em linguagem livre ("focar em frações", "confunde
sujeito e objeto") que é transformada em prompt e aplicada nas próximas questões
daquele aluno.

## Estrutura

```text
tcc_conectado/   App Android (Kotlin, Gradle)
php_appest/      Backend PHP + MySQL
```

## Requisitos

- Android Studio com JDK 21 e Android SDK (compileSdk 36, minSdk 24)
- PHP 8 e MySQL/MariaDB (XAMPP atende)
- Uma chave da API Google Gemini

## Como subir o projeto

### 1. Banco de dados

Rode os scripts na ordem. Todos são aditivos (`CREATE TABLE IF NOT EXISTS`) e
não apagam nada de um banco já existente.

```bash
mysql -u root < php_appest/banco_base_referencia.sql
mysql -u root appest < php_appest/dashboard_desempenho.sql
mysql -u root appest < php_appest/prompt_professor.sql
mysql -u root appest < php_appest/cadastro_professor.sql
```

Para popular o banco com turmas, alunos e notas de demonstração:

```bash
php php_appest/seed_dashboard_demo.php
```

### 2. Backend

Publique a pasta `php_appest/` no servidor web (em XAMPP, dentro de `htdocs/`).
As credenciais do banco ficam em `php_appest/conexao.php`.

### 3. App Android

Copie `tcc_conectado/local.properties.example` para
`tcc_conectado/local.properties` e preencha:

- `sdk.dir` — caminho do Android SDK nesta máquina
- `GEMINI_API_KEY` — sua chave da API Gemini

O endereço do backend fica em `ApiConfig.kt`. O padrão
(`http://10.0.2.2/php_appest/`) aponta para o `localhost` do PC visto de dentro
do emulador. Para um aparelho físico, troque pelo IP da máquina na rede local.

Depois é só abrir a pasta `tcc_conectado/` no Android Studio e rodar.

## Cadastro de professor

O perfil de professor dá acesso às notas da turma e ao envio de orientações
para a IA, então ele não é auto-atribuído: o cadastro exige um código de
convite emitido pela coordenação.

Para emitir um código (só pela linha de comando, no servidor):

```bash
php php_appest/criar_convite_professor.php "Professores 2026" 5 30
```

Os argumentos são: descrição, quantos cadastros o código aceita e em quantos
dias expira (`0` = sem prazo). O código aparece uma única vez na saída — no
banco fica apenas o hash.

Com o código em mãos, o professor toca em **"Sou professor. Criar conta"** na
tela de login do app.

O cadastro pela tela normal do app (`inserir_usuario.php`) cria **sempre** conta
de aluno, qualquer que seja o conteúdo da requisição.

## Segurança

- O app nunca é fonte de verdade sobre quem é o usuário. Ele envia só o token
  recebido no login; perfil, identidade e posse de turma são resolvidos no
  servidor a cada requisição (`php_appest/auth.php`).
- Trocar um `id_turma` ou `id_aluno` na requisição não dá acesso a dados de
  outro professor: a posse é confirmada no banco antes de qualquer leitura ou
  escrita.
- Senhas são gravadas com `password_hash`. Códigos de convite também.
- Nenhum segredo fica no código-fonte. A chave da Gemini vem do
  `local.properties` (não versionado) ou da variável de ambiente
  `GEMINI_API_KEY`.

## Limitações conhecidas

- A comunicação com o backend é HTTP puro (`usesCleartextTraffic`), adequado
  para o ambiente local de desenvolvimento. Uma instalação real precisa de
  HTTPS antes de sair da rede da escola.
- Os endpoints administrativos de CRUD (`inserir_turma.php`,
  `deletar_usuario.php`, `atualizar_*.php`, `listar_usuarios.php` e afins) ainda
  não exigem autenticação. Eles não são chamados pelo app, mas ficam acessíveis
  a quem alcançar o servidor — não publique a pasta em rede aberta sem antes
  protegê-los.
