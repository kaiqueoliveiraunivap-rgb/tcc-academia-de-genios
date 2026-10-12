FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev poppler-utils python3 python3-venv \
    && docker-php-ext-install curl mbstring mysqli \
    && python3 -m venv /opt/academia-python \
    && /opt/academia-python/bin/pip install --no-cache-dir 'pypdf>=5,<7' \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-security.conf /etc/apache2/conf-available/academia-security.conf
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN a2enconf academia-security

COPY php_appest/ /var/www/html/php_appest/
COPY biblioteca/catalogo-drive.json /var/www/html/biblioteca/catalogo-drive.json
COPY docker/entrypoint.sh /usr/local/bin/academia-entrypoint
RUN chmod +x /usr/local/bin/academia-entrypoint \
    && chown -R www-data:www-data /var/www/html

ENV APP_ENV=production
ENV PDF_PYTHON_BIN=/opt/academia-python/bin/python
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/academia-entrypoint"]
