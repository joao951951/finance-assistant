#!/bin/sh
set -e

echo "→ Generating .env from environment variables..."
cat > /var/www/html/.env <<EOF
APP_NAME="${APP_NAME:-Assistente Financeiro}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost}"

LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE}"
DB_USERNAME="${DB_USERNAME}"
DB_PASSWORD="${DB_PASSWORD}"

SESSION_DRIVER="${SESSION_DRIVER:-database}"
SESSION_LIFETIME=120

CACHE_STORE="${CACHE_STORE:-database}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

OPENAI_API_KEY="${OPENAI_API_KEY}"
OPENAI_CHAT_MODEL="${OPENAI_CHAT_MODEL:-gpt-4o}"
OPENAI_EMBEDDING_MODEL="${OPENAI_EMBEDDING_MODEL:-text-embedding-3-small}"
EOF

echo "→ Caching config, routes and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Running migrations..."
php artisan migrate --force

echo "→ Starting services..."
exec "$@"
