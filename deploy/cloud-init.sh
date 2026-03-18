#!/bin/bash
# =============================================================================
# Cloud-init — Assistente Financeiro
# Oracle Cloud Infrastructure — Ubuntu 22.04 / 24.04
#
# Cole este script em "User data" ao criar a instância OCI,
# ou rode manualmente com: sudo bash cloud-init.sh
# =============================================================================
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURE ANTES DE DEPLOY
# ─────────────────────────────────────────────────────────────────────────────
REPO_URL="https://github.com/joao951951/finance-assistant"
APP_DIR="/var/www/finance-assistant"
APP_URL="https://assistentefinanceiro.online"

DB_HOST="aws-1-us-east-1.pooler.supabase.com"
DB_PORT="6543"
DB_DATABASE="postgres"
DB_USERNAME="postgres.mcizhlwuvvweaxbzezzj"
DB_PASSWORD="YTxapm9UG4tkcPdT"

OPENAI_API_KEY=""          # opcional — cada usuário configura o seu nas settings
OPENAI_CHAT_MODEL="gpt-4o"
OPENAI_EMBEDDING_MODEL="text-embedding-3-small"
# ─────────────────────────────────────────────────────────────────────────────

log() { echo ""; echo "══ $1"; }

# ── 1. Sistema ────────────────────────────────────────────────────────────────
log "Atualizando sistema..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y curl git unzip software-properties-common netfilter-persistent

# ── 2. PHP 8.4 ────────────────────────────────────────────────────────────────
log "Instalando PHP 8.4..."
add-apt-repository ppa:ondrej/php -y
apt-get update -y
apt-get install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-pgsql \
    php8.4-zip \
    php8.4-gd \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-bcmath \
    php8.4-curl \
    php8.4-opcache \
    php8.4-intl \
    php8.4-tokenizer

# ── 3. Composer ───────────────────────────────────────────────────────────────
log "Instalando Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── 4. Node.js 20 ─────────────────────────────────────────────────────────────
log "Instalando Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# ── 5. Nginx + Supervisor ─────────────────────────────────────────────────────
log "Instalando Nginx e Supervisor..."
apt-get install -y nginx supervisor

# ── 6. Liberar porta 80 (iptables do Oracle Cloud) ───────────────────────────
log "Liberando porta 80 no firewall..."
if command -v iptables &>/dev/null; then
    iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
    iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
    netfilter-persistent save
else
    echo "⚠ iptables não disponível (ambiente de teste) — pulando."
fi

# ── 7. Verificar repositorio ─────────────────────────────────────────────────────
if [ -d "$APP_DIR" ]; then
    log "Repositório já existe, pulando clonagem..."
    cd "$APP_DIR"
    git pull
else
    log "Repositório não existe, clonando..."
    mkdir -p /var/www
    git clone "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
fi

# ── 8. Arquivo .env ───────────────────────────────────────────────────────────
log "Configurando .env..."
cat > "$APP_DIR/.env" <<EOF
APP_NAME="Assistente Financeiro"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

OPENAI_API_KEY=${OPENAI_API_KEY}
OPENAI_CHAT_MODEL=${OPENAI_CHAT_MODEL}
OPENAI_EMBEDDING_MODEL=${OPENAI_EMBEDDING_MODEL}
EOF

# ── 9. Dependências PHP ───────────────────────────────────────────────────────
log "Instalando dependências PHP..."
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

# ── 10. Dependências Node + build ─────────────────────────────────────────────
log "Buildando frontend..."
npm ci
npm run build

# ── 11. Laravel setup ─────────────────────────────────────────────────────────
log "Configurando Laravel..."
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 12. Permissões ────────────────────────────────────────────────────────────
log "Ajustando permissões..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR/storage"
chmod -R 755 "$APP_DIR/bootstrap/cache"

# ── 13. Nginx ─────────────────────────────────────────────────────────────────
log "Configurando Nginx..."
cat > /etc/nginx/sites-available/finance-assistant <<'NGINX'
server {
    listen 80;
    server_name assistentefinanceiro.online;

    root /var/www/finance-assistant/public;
    index index.php;

    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/finance-assistant /etc/nginx/sites-enabled/finance-assistant
rm -f /etc/nginx/sites-enabled/default
nginx -t

# ── 14. Supervisor (queue worker) ─────────────────────────────────────────────
log "Configurando Supervisor (queue worker)..."
cat > /etc/supervisor/conf.d/finance-worker.conf <<SUPERVISOR
[program:finance-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/finance-assistant/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/finance-assistant/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR

# ── 15. Iniciar serviços ──────────────────────────────────────────────────────
log "Iniciando serviços..."
if command -v systemctl &>/dev/null && systemctl is-system-running &>/dev/null 2>&1; then
    # Produção (OCI) — usa systemd
    systemctl enable php8.4-fpm nginx supervisor
    systemctl restart php8.4-fpm nginx
    supervisorctl reread
    supervisorctl update
    supervisorctl start finance-worker:*
else
    # Teste (Docker sem systemd) — inicia diretamente
    mkdir -p /var/run/php
    service php8.4-fpm start
    service nginx start
    supervisord -c /etc/supervisor/supervisord.conf
    supervisorctl reread
    supervisorctl update
    supervisorctl start finance-worker:* || true
fi

# ── 16. Script de atualização (deploy futuro) ─────────────────────────────────
cat > /usr/local/bin/deploy <<DEPLOY
#!/bin/bash
set -e
cd 
echo "→ Pulling latest code..."
git pull origin main
echo "→ Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
echo "→ Updating Laravel..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
echo "→ Restarting worker..."
supervisorctl restart finance-worker:*
echo "✓ Deploy concluído!"
DEPLOY
chmod +x /usr/local/bin/deploy

log "✓ Instalação concluída!"
echo ""
echo "  Acesse: http://$(curl -s ifconfig.me)"
echo "  Para deploys futuros: sudo deploy"
echo ""
echo "  LEMBRE-SE: Libere a porta 80 no Security List da VCN no console OCI."
