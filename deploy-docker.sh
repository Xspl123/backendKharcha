#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="/var/www/html/ExpTlaravel-main"
REACT_DIR="/home/daily-expence-react-main"
REACT_DEPLOY_DIR="/var/www/manage.apnakharcha.in"

cd "$REACT_DIR"
git pull
npm ci
npm run build
rsync -av --delete dist/ "$REACT_DEPLOY_DIR/"
chown -R www-data:www-data "$REACT_DEPLOY_DIR"
chmod -R 755 "$REACT_DEPLOY_DIR"

cd "$BACKEND_DIR"
git pull

if command -v docker-compose >/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    COMPOSE="docker compose"
fi

$COMPOSE -f docker-compose.prod.yml up -d --build
$COMPOSE -f docker-compose.prod.yml exec -T app composer install --no-dev --optimize-autoloader
$COMPOSE -f docker-compose.prod.yml exec -T app php artisan migrate --force
$COMPOSE -f docker-compose.prod.yml exec -T app php artisan optimize
$COMPOSE -f docker-compose.prod.yml exec -T app php artisan queue:restart
