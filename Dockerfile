FROM php:8.2-apache

# Instalar dependencias del sistema y driver PostgreSQL (libpq-dev)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Configurar zona horaria y parámetros recomendados de PHP en producción
RUN printf "date.timezone = America/La_Paz\n\
expose_php = Off\n\
upload_max_filesize = 10M\n\
post_max_size = 12M\n\
memory_limit = 256M\n" > /usr/local/etc/php/conf.d/vidasalud.ini

# Configurar DocumentRoot de Apache hacia /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf "<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n" > /etc/apache2/conf-available/vidasalud-public.conf \
    && a2enconf vidasalud-public

WORKDIR /var/www/html

# Copiar código fuente de la aplicación
COPY . /var/www/html/

# Preparar directorios de almacenamiento para sesiones y bandeja de correos
RUN mkdir -p /var/www/html/storage/sessions /var/www/html/storage/mail_outbox \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Instalar script de entrada (soporte de puerto dinámico $PORT en Render y AUTO_MIGRATE)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
