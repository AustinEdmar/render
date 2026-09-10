#!/bin/bash
set -e

# Render injeta a variável PORT em runtime; o nginx.conf tem um placeholder __PORT__
: "${PORT:=10000}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf

# Gera APP_KEY se ainda não existir (idealmente já vem via env var no painel do Render)
if [ -z "$APP_KEY" ]; then
    echo "AVISO: APP_KEY não definida. Defina-a nas variáveis de ambiente do Render."
fi

# Roda migrations automaticamente no start (opcional — pode preferir Pre-Deploy Command do Render em vez disto)
php artisan migrate --force || echo "Migração falhou ou não havia pendências."

exec supervisord -c /etc/supervisord.conf
