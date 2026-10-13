<?php
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#242457"><title>Coordenação · Academia de Gênios</title>
  <link rel="stylesheet" href="painel.css?v=2"><link rel="stylesheet" href="painel-polimento.css?v=2"><script src="painel.js?v=3" defer></script>
</head>
<body>
<div id="inicial" class="carregando" role="status">Abrindo a Academia de Gênios…</div>
<section id="login" class="login" hidden>
  <div class="login-historia">
    <a class="marca" href="./"><span class="simbolo" aria-hidden="true">AG</span><span>Academia<br> de Gênios</span></a>
    <div class="historia-texto"><span class="sobretitulo claro">ESPAÇO DA COORDENAÇÃO</span><h1>Uma escola organizada.<br>Mais espaço para aprender.</h1><p>Pessoas, turmas e livros conectados à jornada de cada aluno.</p></div>
    <div class="livros-ilustracao" aria-hidden="true"><span>Conhecer</span><span>Aprender</span><span>Transformar</span></div>
    <small>Gestão escolar que acompanha o aprendizado.</small>
  </div>
  <div class="login-formulario"><div class="login-caixa">
    <span class="sobretitulo">BEM-VINDO DE VOLTA</span><h2>Vamos cuidar da escola?</h2><p class="muted">Entre com sua conta da coordenação ou secretaria.</p>
    <form id="form-login">
      <label>E-mail institucional<input name="email" type="email" autocomplete="username" maxlength="150" required placeholder="seu.nome@escola.com.br"></label>
      <label>Senha<input name="senha" type="password" autocomplete="current-password" required maxlength="72" placeholder="Sua senha de acesso"></label>
      <p id="erro-login" class="erro" role="alert" hidden></p>
      <button class="primario largo" type="submit">Entrar no painel <span aria-hidden="true">→</span></button>
    </form>
    <div class="nota-acesso"><strong>Acesso exclusivo da escola</strong><p>Precisa de acesso ou esqueceu sua senha? Procure uma pessoa da coordenação que já tenha uma conta.</p></div>
  </div></div>
</section>
<div id="app" class="app" hidden>
  <aside class="sidebar">
    <a class="marca" href="#inicio"><span class="simbolo" aria-hidden="true">AG</span><span>Academia<br> de Gênios</span></a>
    <span class="rotulo-nav">GESTÃO DA ESCOLA</span>
    <nav aria-label="Menu principal">
      <button data-page="inicio">Visão geral</button><button data-page="aluno">Alunos</button><button data-page="professor">Professores</button><button data-page="turmas">Turmas</button><button data-page="conteudos">Conteúdos</button><button data-page="livros">Biblioteca</button><button data-page="admin">Equipe da secretaria</button><button data-page="configuracoes">Configurações</button>
    </nav>
    <div class="sidebar-rodape"><span class="status-dot"></span> Conectado ao banco do app<p>Os cadastros ficam disponíveis no aplicativo após salvar.</p></div>
    <button id="sair" class="sair">Sair da conta <span aria-hidden="true">↗</span></button>
  </aside>
  <div class="principal">
    <header class="topbar"><span>PAINEL ESCOLAR <span class="separador">/</span> <span id="local-atual">Visão geral</span></span><button id="minha-conta" class="conta" type="button" aria-label="Abrir minha conta" aria-haspopup="dialog"><span class="avatar" id="avatar"><span id="avatar-iniciais">C</span><img id="avatar-foto" alt="" hidden></span><span id="nome-conta"></span><span aria-hidden="true">⌄</span></button></header>
    <main id="conteudo" tabindex="-1" aria-busy="false"></main>
    <footer>Academia de Gênios <span>Coordenação & secretaria</span></footer>
  </div>
</div>
<dialog id="modal"><form id="form-modal"><div class="modal-topo"><div><span class="sobretitulo" id="modal-legenda">GESTÃO ESCOLAR</span><h2 id="modal-titulo"></h2></div><button type="button" id="fechar-modal" class="icone-btn" aria-label="Fechar janela">×</button></div><div id="modal-conteudo"></div><p class="erro" id="erro-modal" role="alert" hidden></p><div class="modal-acoes"><button type="button" class="secundario" id="cancelar-modal">Cancelar</button><button type="submit" class="primario" id="salvar-modal">Salvar</button></div></form></dialog>
<div id="toast" class="toast" role="status" hidden></div>
</body></html>
