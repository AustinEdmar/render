#!/bin/sh
echo "=== START.SH A CORRER ==="

echo "--- config:cache ---"
php artisan config:cache
echo "config:cache saiu com codigo: $?"

echo "--- route:cache ---"
php artisan route:cache
echo "route:cache saiu com codigo: $?"

echo "--- migrate ---"
php artisan migrate --force
echo "migrate saiu com codigo: $?"

echo "--- testando config do nginx ---"
nginx -t

echo "--- iniciando php-fpm ---"
php-fpm -D
echo "php-fpm iniciado"

echo "--- iniciando nginx ---"
exec nginx -g "daemon off;"