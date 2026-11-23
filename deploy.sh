#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/matcha-maintenance
BRANCH=main
BACKUP_DB_REF="qhloonmzkumzulppddng"

cd "$APP_DIR"

git remote get-url origin | grep -q "matcha-pt-sip-backup" \
  || { echo "ABORT: remote bukan repo backup"; exit 1; }

[ -f .env ] || { echo "ABORT: .env tidak ada"; exit 1; }
grep -q "$BACKUP_DB_REF" .env \
  || { echo "ABORT: .env tidak menunjuk ke Supabase backup"; exit 1; }

git pull --ff-only origin "$BRANCH"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan config:clear
php artisan view:clear

echo "Deploy selesai: $(git log -1 --oneline)"