package com.github.carloseduardofsousa.tcc

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.ProgressBar
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONObject

class LoginActivity : AppCompatActivity() {

    private lateinit var etEmail: EditText
    private lateinit var etSenha: EditText
    private lateinit var btnEntrar: Button
    private lateinit var progressBar: ProgressBar

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        etEmail    = findViewById(R.id.etEmail)
        etSenha    = findViewById(R.id.etSenha)
        btnEntrar  = findViewById(R.id.btnEntrar)
        progressBar = findViewById(R.id.progressBarLogin)

        btnEntrar.setOnClickListener { fazerLogin() }

        findViewById<Button>(R.id.btnIrCadastroProfessor).setOnClickListener {
            startActivity(Intent(this, CadastroProfessorActivity::class.java))
        }
    }

    private fun fazerLogin() {
        val email = etEmail.text.toString().trim()
        val senha = etSenha.text.toString().trim()

        if (email.isEmpty() || senha.isEmpty()) {
            Toast.makeText(this, "Preencha e-mail e senha.", Toast.LENGTH_SHORT).show()
            return
        }

        btnEntrar.isEnabled = false
        progressBar.visibility = View.VISIBLE

        Thread {
            val resposta = ApiClient.post("login.php", mapOf("email" to email, "senha" to senha))

            runOnUiThread {
                progressBar.visibility = View.GONE
                btnEntrar.isEnabled = true

                if (resposta == null) {
                    Toast.makeText(this, "Erro de conexão. Verifique o servidor.", Toast.LENGTH_LONG).show()
                    return@runOnUiThread
                }

                try {
                    val json = JSONObject(resposta)
                    if (json.getString("status") == "sucesso") {
                        val idUsuario   = json.getInt("id_usuario")
                        val nomeUsuario = json.getString("nome")
                        val tipoPerfil  = json.getString("tipo_perfil")
                        val token       = json.optString("token")
                        val idTurma     = json.optInt("id_turma", 0)

                        // O token é o que autentica as chamadas seguintes ao
                        // servidor; sem ele, o dashboard do professor não abre.
                        Sessao.salvar(this, token, idUsuario, nomeUsuario, tipoPerfil, idTurma)

                        val destino = if (tipoPerfil.equals("professor", ignoreCase = true)) {
                            ProfessorActivity::class.java
                        } else {
                            MainActivity::class.java
                        }

                        val intent = Intent(this, destino)
                        intent.putExtra("ID_USUARIO",  idUsuario)
                        intent.putExtra("NOME_USUARIO", nomeUsuario)
                        intent.putExtra("TIPO_PERFIL",  tipoPerfil)
                        intent.putExtra("ID_TURMA",     idTurma)
                        startActivity(intent)
                        finish()
                    } else {
                        Toast.makeText(this, json.optString("mensagem", "Login inválido."), Toast.LENGTH_SHORT).show()
                    }
                } catch (e: Exception) {
                    Toast.makeText(this, "Resposta inesperada do servidor.", Toast.LENGTH_SHORT).show()
                }
            }
        }.start()
    }
}
