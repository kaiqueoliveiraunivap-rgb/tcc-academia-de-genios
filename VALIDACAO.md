# Validação local — 04/10/2026

O app Android, a API e o painel da coordenação continuam usando o mesmo banco `appest`. As migrações são incrementais, sem substituir os cadastros existentes.

## Testes executados

- Auditoria de otimização: `php -l` aprovado nos 77 arquivos PHP; `estudo_validacao_test.php` e `estudo_formato_test.php` aprovados após a compactação do prompt, alteração do retry e das métricas seguras de geração.

- Validação de questões e formatação das explicações: scripts `estudo_validacao_test.php` e `estudo_formato_test.php` aprovados.
- Integração de aluno/professor: `estudo_turma_test.php` aprovado em banco temporário. Cobre orientação para toda a turma, nível médio, permissões, histórico, revisão, progresso, conclusão sem duplicar pontos, ranking e gráficos individuais/agregados.
- Login HTTP: contas e perfis verificados; o acesso administrativo acontece pelo painel web.
- Coordenação: `coordenacao_http_test.php` aprovado, inclusive com `--auto-worker`. Cobre CRUD, hash das senhas, revogação de sessões, CSV sem gravação parcial, vínculo com o app, PDFs com marcadores, revisão manual de páginas, desativação do livro, proteção CSRF e limite de tentativas de login.
- Android: compilação limpa `assembleDebug` aprovada; APK instalado no emulador Android API 36.1. O teste unitário básico existente também passou; a verificação dos fluxos foi complementada com uso real no emulador e testes da API.
- Matemática: geração real da explicação e de cinco questões do capítulo “Conjuntos numéricos e Aritmética”, com oito blocos de explicação. Primeira geração: aproximadamente 60 segundos; consulta seguinte reaproveitou o conteúdo em menos de um segundo neste ambiente.
- Emulador: login do aluno, seleção de matéria/frente/capítulo, explicação, quiz completo, gravação do resultado, gráfico pelo botão do resultado e ranking com pontuação acumulada verificados. O quiz de demonstração ficou registrado na conta de teste.
- Professor no emulador: login, seleção da série/turma, gráfico agregado e abertura da orientação aplicada a todos os alunos da turma verificados. A gravação e o alcance coletivo da orientação foram validados no teste de integração.
- Painel web: verificação visual em tamanho de computador e celular, edição de aluno, vínculo de turma e navegação da biblioteca.

## Uso e limites

Abra `tcc_conectado` no Android Studio para executar o app no emulador. Execute `iniciar-coordenacao.cmd` para abrir o painel. O guia `COORDENACAO.md` explica contas administrativas, CSV, PDFs e configuração do servidor.

Os testes confirmam os fluxos acima no ambiente local; não constituem garantia de ausência de todos os erros. A geração de novos conteúdos depende da disponibilidade e da cota da API de IA. O teste com Matemática não equivale à revisão pedagógica de todos os capítulos.

PDFs com texto e marcadores são preparados automaticamente. PDFs sem marcadores precisam da divisão em capítulos no painel; documentos digitalizados precisam de OCR antes do envio.

A implantação para uso nos dispositivos da escola ainda depende de hospedagem/rede, HTTPS, URL da API e backups, conforme `HOSPEDAGEM.md`. Essa implantação não foi realizada nesta etapa.
