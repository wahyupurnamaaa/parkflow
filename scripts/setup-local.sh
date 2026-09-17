#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
(cd backend && composer install)
if [ ! -f backend/.env ]; then
 cp backend/.env.example backend/.env
fi
if ! grep -q '^APP_KEY=base64:' backend/.env; then
 (cd backend && php artisan key:generate)
fi
(cd backend && touch database/database.sqlite && php artisan migrate --seed)
(cd frontend && npm ci)
printf '\nSelesai. Jalankan: ./scripts/start-local.sh\n'
