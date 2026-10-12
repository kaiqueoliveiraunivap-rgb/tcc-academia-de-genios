# Hospedar a API e o banco sem XAMPP

A opção preparada neste projeto é **Railway + MySQL**, porque ela executa o PHP em
Docker, fornece um banco MySQL no mesmo projeto e mantém as credenciais em variáveis
privadas. O banco não precisa ficar exposto publicamente.

## Publicação

1. Envie esta pasta para um repositório GitHub. O `Dockerfile` deve ficar na raiz
   selecionada pela Railway. Se o repositório contiver uma pasta externa, configure
   **Root Directory** como `/tcc-academia-de-genios-main`.
2. Na Railway, crie um projeto vazio e adicione **Database > MySQL**.
3. Adicione outro serviço com **GitHub Repo** e selecione este repositório.
4. No serviço da API, abra **Variables** e adicione:

   ```text
   APP_ENV=production
   MYSQLHOST=${{MySQL.MYSQLHOST}}
   MYSQLPORT=${{MySQL.MYSQLPORT}}
   MYSQLDATABASE=${{MySQL.MYSQLDATABASE}}
   MYSQLUSER=${{MySQL.MYSQLUSER}}
   MYSQLPASSWORD=${{MySQL.MYSQLPASSWORD}}
   GEMINI_API_KEY=SUA_CHAVE_GEMINI
   GEMINI_MODEL=gemini-3.5-flash-lite
   ```

   Se o serviço do banco tiver outro nome, substitua `MySQL` pelo nome mostrado no
   painel. Nunca coloque a chave Gemini ou a senha do banco no GitHub.
5. Em **Settings > Networking**, clique em **Generate Domain**. Teste:
   `https://SEU-DOMINIO/php_appest/saude.php`.
6. Em `tcc_conectado/local.properties`, mantenha o caminho do SDK e adicione:

   ```properties
   API_BASE_URL=https://SEU-DOMINIO/php_appest/
   ```

7. Sincronize o Gradle e execute o app. A URL entra no APK durante a compilação.

O contêiner espera o MySQL, cria/atualiza as tabelas de forma idempotente e só então
inicia o Apache. Em produção ele não cria as contas locais de teste. O endpoint de
saúde impede que uma implantação quebrada receba tráfego.

## Desenvolvimento local opcional

O build debug no Windows inicia a API local somente quando `API_BASE_URL` aponta para
`10.0.2.2:8088`. Para iniciá-la sem compilar, execute na raiz:

```powershell
.\iniciar-local.ps1
```

Ou use a tarefa Gradle `prepararAmbienteLocal`. Para voltar ao servidor local no emulador:

```properties
API_BASE_URL=http://10.0.2.2:8088/php_appest/
```

Antes de uso real, habilite backups do MySQL no provedor e troque qualquer credencial
que tenha sido usada em testes.
