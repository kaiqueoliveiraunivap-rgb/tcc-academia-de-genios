package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.content.res.ColorStateList
import android.graphics.Color
import android.os.Bundle
import android.view.View
import android.view.inputmethod.EditorInfo
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowCompat
import android.widget.Button
import android.widget.EditText
import android.widget.ProgressBar
import android.widget.Toast
import android.widget.TextView
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONObject

class LoginActivity : AppCompatActivity() {

    private lateinit var etEmail: EditText
    private lateinit var etSenha: EditText
    private lateinit var btnEntrar: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var btnPerfilAluno: Button
    private lateinit var btnPerfilProfessor: Button
    private lateinit var status: TextView
    private val perfis = listOf("aluno", "professor")
    private var perfil = "aluno"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (Sessao.token(this).isNotEmpty() && !Sessao.estaLogado(this)) Sessao.limpar(this)
        setContentView(R.layout.activity_login)
        val raiz = findViewById<View>(R.id.loginScroll)
        EstudoUi.protegerBarras(raiz, incluirTeclado = true)
        WindowCompat.getInsetsController(window, raiz).isAppearanceLightStatusBars = !TemaApp.escuro()

        etEmail    = findViewById(R.id.etEmail)
        etSenha    = findViewById(R.id.etSenha)
        btnEntrar  = findViewById(R.id.btnEntrar)
        progressBar = findViewById(R.id.progressBarLogin)
        btnPerfilAluno = findViewById(R.id.btnPerfilAluno)
        btnPerfilProfessor = findViewById(R.id.btnPerfilProfessor)
        status = findViewById(R.id.statusLogin)
        perfil = savedInstanceState?.getString("perfil")?.takeIf { it in perfis } ?: "aluno"
        btnPerfilAluno.setOnClickListener { selecionarPerfil("aluno") }
        btnPerfilProfessor.setOnClickListener { selecionarPerfil("professor") }
        atualizarPerfil()

        btnEntrar.setOnClickListener { fazerLogin() }
        etEmail.imeOptions = EditorInfo.IME_ACTION_NEXT
        etSenha.imeOptions = EditorInfo.IME_ACTION_DONE
        etSenha.setOnEditorActionListener { _, acao, _ ->
            if (acao == EditorInfo.IME_ACTION_DONE) {
                if (btnEntrar.isEnabled) fazerLogin()
                true
            } else false
        }

        findViewById<Button>(R.id.btnPainelSecretaria).setOnClickListener { PainelSecretaria.abrir(this) }
    }

    private fun atualizarPerfil() {
        listOf(
            btnPerfilAluno to "aluno",
            btnPerfilProfessor to "professor"
        ).forEach { (botao, tipo) ->
            val selecionado = perfil == tipo
            botao.backgroundTintList = ColorStateList.valueOf(
                if (selecionado) UiPaleta.hex("#5C6BC0") else UiPaleta.hex("#EEEEEE")
            )
            botao.setTextColor(if (selecionado) Color.WHITE else UiPaleta.hex("#555555"))
        }
        btnEntrar.text = "Entrar como $perfil"
        status.text = "Use o acesso de $perfil fornecido pela secretaria."
    }

    private fun selecionarPerfil(tipo: String) {
        perfil = tipo
        atualizarPerfil()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putString("perfil", perfil)
    }

    private fun fazerLogin() {
        val email = etEmail.text.toString().trim()
        val senha = etSenha.text.toString()
        val perfilSolicitado = perfil

        if (email.isEmpty() || senha.isEmpty()) {
            Toast.makeText(this, "Preencha e-mail e senha.", Toast.LENGTH_SHORT).show()
            return
        }

        btnEntrar.isEnabled = false
        ViewCompat.getWindowInsetsController(etSenha)?.hide(WindowInsetsCompat.Type.ime())
        botoesPerfil().forEach { it.isEnabled = false }
        status.text = "Entrando…"
        progressBar.visibility = View.VISIBLE

        lifecycleScope.launch {
            val resposta = withContext(Dispatchers.IO) {
                ApiClient.postComStatus("login.php", mapOf("email" to email, "senha" to senha, "tipo_perfil" to perfilSolicitado))
            }

                progressBar.visibility = View.GONE
                btnEntrar.isEnabled = true
                botoesPerfil().forEach { it.isEnabled = true }

                if (resposta.corpo == null) {
                    status.text = "Não foi possível conectar à API. Execute o app pelo Android Studio para iniciar o servidor local."
                    return@launch
                }

                try {
                    val json = JSONObject(resposta.corpo)
                    if (resposta.sucesso && json.getString("status") == "sucesso") {
                        val idUsuario   = json.getInt("id_usuario")
                        val nomeUsuario = json.getString("nome")
                        val tipoPerfil  = json.getString("tipo_perfil")
                        val token       = json.optString("token")
                        val idTurma     = json.optInt("id_turma", 0)
                        if (tipoPerfil != perfilSolicitado || token.isBlank()) {
                            status.text = "O servidor devolveu um perfil diferente do selecionado."
                            return@launch
                        }

                        // O token é o que autentica as chamadas seguintes ao
                        // servidor; sem ele, o dashboard do professor não abre.
                        Sessao.salvar(this@LoginActivity, token, idUsuario, nomeUsuario, tipoPerfil, idTurma)

                        val destino = MenuActivity::class.java

                        val intent = Intent(this@LoginActivity, destino)
                        intent.putExtra("ID_USUARIO",  idUsuario)
                        intent.putExtra("NOME_USUARIO", nomeUsuario)
                        intent.putExtra("TIPO_PERFIL",  tipoPerfil)
                        intent.putExtra("ID_TURMA",     idTurma)
                        startActivity(intent)
                        finish()
                    } else {
                        status.text = json.optString("mensagem", "Login inválido.")
                    }
                } catch (e: Exception) {
                    status.text = "Resposta inesperada do servidor."
                }
        }
    }

    private fun botoesPerfil() = listOf(btnPerfilAluno, btnPerfilProfessor)
}
