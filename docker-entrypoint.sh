#!/usr/bin/env bash
set -e

# 1. Ajustar puerto dinámico de Apache si Render inyecta $PORT (por defecto 80)
LISTEN_PORT="${PORT:-80}"
if [ "$LISTEN_PORT" != "80" ]; then
    echo "[Entrypoint] Configurando Apache para escuchar en el puerto dinámico ${LISTEN_PORT}..."
    sed -ri "s/Listen 80/Listen ${LISTEN_PORT}/g" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${LISTEN_PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

# 2. Garantizar permisos de escritura en storage/ (sesiones y outbox de correo)
mkdir -p /var/www/html/storage/sessions /var/www/html/storage/mail_outbox
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# 3. Migración automática contra Neon PostgreSQL si AUTO_MIGRATE=true y las tablas aún no existen
if [ "${AUTO_MIGRATE}" = "true" ] || [ "${AUTO_MIGRATE}" = "1" ]; then
    echo "[Entrypoint] AUTO_MIGRATE activo: verificando estado de base de datos PostgreSQL..."
    php /var/www/html/database/migrate.php --if-empty || echo "[Entrypoint] Advertencia: No se pudo completar la auto-migración en el arranque. Verifique las variables de conexión en Render."
fi

exec "$@"
