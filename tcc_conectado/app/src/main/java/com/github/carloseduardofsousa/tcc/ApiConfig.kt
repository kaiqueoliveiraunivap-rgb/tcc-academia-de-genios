package com.github.carloseduardofsousa.tcc

object ApiConfig {
    // Troque pelo IP do seu servidor onde o PHP está hospedado
    // Exemplo para emulador acessando localhost do PC: "http://10.0.2.2/php_appest/"
    // Exemplo para dispositivo físico na mesma rede: "http://192.168.1.100/php_appest/"
    val BASE_URL: String = BuildConfig.API_BASE_URL
}
