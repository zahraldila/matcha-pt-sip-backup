#!/usr/bin/env bash
set -euo pipefail

main() {
  REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  APP_DIR="$REPO_DIR/matcha-pt-web"
  BRANCH="web/develop"
  BACKUP_DB_REF="qhloonmzkumzulppddng"
  OLD_DB_REF="xkyneehswdqkdgzodwdc"

  cd "$REPO_DIR"

  if ! git remote get-url origin | grep -q "matcha-pt-sip-backup"; then
    echo "ABORT: remote bukan repo backup"; exit 1
  fi

  if [ "$(git branch --show-current)" != "$BRANCH" ]; then
    echo "ABORT: branch aktif bukan $BRANCH"; exit 1
  fi

  if [ ! -f "$APP_DIR/.env" ]; then
    echo "ABORT: .env tidak ada"; exit 1
  fi

  if ! grep -q "$BACKUP_DB_REF" "$APP_DIR/.env"; then
    echo "ABORT: .env tidak menunjuk ke Supabase backup"; exit 1
  fi

  if grep -q "$OLD_DB_REF" "$APP_DIR/.env"; then
    echo "ABORT: .env masih menunjuk ke project Supabase lama"; exit 1
  fi

  git pull --ff-only origin "$BRANCH"

  cd "$APP_DIR"
  composer install --no-dev --optimize-autoloader --no-interaction
  npm ci
  npm run build

  php artisan migrate --force
  php artisan config:clear
  php artisan view:clear

  echo "Deploy selesai: $(git -C "$REPO_DIR" log -1 --oneline)"
}

main "$@"