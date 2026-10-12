package com.github.carloseduardofsousa.tcc

import android.os.Bundle
import android.util.Patterns
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.Toast
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import org.json.JSONObject

/**
 * Cadastro de uma conta de professor.
 *
 * A validação daqui existe para o professor corrigir o erro sem esperar a
 * viagem até o servidor. Quem decide se o cadastro vale é o backend
 * (cadastrar_professor.php), que revalida tudo e confere o código de convite.
 */
class CadastroProfessorActivity : AppCompatActivity() {

    private lateinit var tilNome: TextInputLayout
    private lateinit var tilEmail: TextInputLayout
    private lateinit var tilSenha: TextInputLayout
    private lateinit var tilConfirmarSenha: TextInputLayout
    private lateinit var tilConvite: TextInputLayout

    private lateinit var etNome: TextInputEditText
    private lateinit var etEmail: TextInputEditText
    private lateinit var etSenha: TextInputEditText
    private lateinit var etConfirmarSenha: TextInputEditText
    private lateinit var etConvite: TextInputEditText

    private lateinit var btnCadastrar: Button
    private lateinit var progressBar: ProgressBar
    private var cadastroAluno = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_cadastro_professor)
        cadastroAluno = intent.getStringExtra("PERFIL_CADASTRO") == "aluno"

        tilNome            = findViewById(R.id.tilNomeCadastro)
        tilEmail           = findViewById(R.id.tilEmailCadastro)
        tilSenha           = findViewById(R.id.tilSenhaCadastro)
        tilConfirmarSenha  = findViewById(R.id.tilConfirmarSenhaCadastro)
        tilConvite         = findViewById(R.id.tilConviteCadastro)

        etNome             = findViewById(R.id.etNomeCadastro)
        etEmail            = findViewById(R.id.etEmailCadastro)
        etSenha            = findViewById(R.id.etSenhaCadastro)
        etConfirmarSenha   = findViewById(R.id.etConfirmarSenhaCadastro)
        etConvite          = findViewById(R.id.etConviteCadastro)

        btnCadastrar = findViewById(R.id.btnCadastrar)
        progressBar  = findViewById(R.id.progressBarCadastro)
        if (cadastroAluno) {
            findViewById<TextView>(R.id.txtTituloCadastro).text = "Cadastro de aluno"
            findViewById<TextView>(R.id.txtSubtituloCadastro).text = "Crie sua conta para começar a estudar."
            tilConvite.visibility = View.GONE
        }

        btnCadastrar.setOnClickListener { cadastrar() }
        findViewById<Button>(R.id.btnVoltarLogin).setOnClickListener { finish() }
    }

    private fun cadastrar() {
        val nome            = etNome.text?.toString()?.trim().orEmpty()
        val email           = etEmail.text?.toString()?.trim().orEmpty()
        val senha           = etSenha.text?.toString().orEmpty()
        val confirmarSenha  = etConfirmarSenha.text?.toString().orEmpty()
        val convite         = etConvite.text?.toString()?.trim().orEmpty()

        if (!validar(nome, email, senha, confirmarSenha, convite)) return

        progressBar.visibility = View.VISIBLE
        btnCadastrar.isEnabled = false

        Thread {
            val resposta = ApiClient.postComStatus(
                if (cadastroAluno) "inserir_usuario.php" else "cadastrar_professor.php",
                mapOf(
                    "nome"           to nome,
                    "email"          to email,
                    "senha"          to senha,
                    "codigo_convite" to convite
                )
            )

            runOnUiThread {
                progressBar.visibility = View.GONE
                btnCadastrar.isEnabled = true
                tratarResposta(resposta)
            }
        }.start()
    }

    private fun tratarResposta(resposta: ApiResposta) {
        if (resposta.corpo.isNullOrBlank()) {
            Toast.makeText(this, "Erro de conexão. Verifique o servidor.", Toast.LENGTH_LONG).show()
            return
        }

        val json = try {
            JSONObject(resposta.corpo)
        } catch (e: Exception) {
            Toast.makeText(this, "Resposta inesperada do servidor.", Toast.LENGTH_LONG).show()
            return
        }

        if (json.optString("status") != "sucesso") {
            val mensagem = json.optString("mensagem", "Não foi possível concluir o cadastro.")
            // Erros de e-mail e de convite são mostrados no próprio campo:
            // o professor vê onde corrigir sem ter que ler um Toast que some.
            when (resposta.codigo) {
                409  -> tilEmail.error = mensagem
                403  -> tilConvite.error = mensagem
                else -> Toast.makeText(this, mensagem, Toast.LENGTH_LONG).show()
            }
            return
        }

        Toast.makeText(
            this,
            json.optString("mensagem", "Cadastro concluído. Faça login para entrar."),
            Toast.LENGTH_LONG
        ).show()
        finish()
    }

    private fun validar(
        nome: String,
        email: String,
        senha: String,
        confirmarSenha: String,
        convite: String
    ): Boolean {
        listOf(tilNome, tilEmail, tilSenha, tilConfirmarSenha, tilConvite).forEach { it.error = null }

        var valido = true

        if (nome.length < 3) {
            tilNome.error = "Informe seu nome completo."
            valido = false
        }
        if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            tilEmail.error = "Informe um e-mail válido."
            valido = false
        }
        if (senha.length < SENHA_MINIMA) {
            tilSenha.error = "Mínimo de $SENHA_MINIMA caracteres."
            valido = false
        } else if (!senha.any { it.isLetter() } || !senha.any { it.isDigit() }) {
            tilSenha.error = "Use letras e números."
            valido = false
        }
        if (senha != confirmarSenha) {
            tilConfirmarSenha.error = "As senhas não conferem."
            valido = false
        }
        if (!cadastroAluno && convite.isEmpty()) {
            tilConvite.error = "Informe o código de convite."
            valido = false
        }

        return valido
    }

    private companion object {
        // Mesma regra do cadastrar_professor.php.
        const val SENHA_MINIMA = 8
    }
}
