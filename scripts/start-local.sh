#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT_DIR="$PWD"
if [ ! -f backend/vendor/autoload.php ] || [ ! -d frontend/node_modules ]; then
  echo 'Jalankan scripts/setup-local.sh terlebih dahulu.'
  exit 1
fi
php -r 'foreach ([8000,3000] as $port) { $s = @fsockopen("127.0.0.1", $port); if ($s) { fclose($s); fwrite(STDERR,"Port $port sedang dipakai. Hentikan server sebelumnya atau buka http://127.0.0.1:3000\n"); exit(1); } }'
(cd backend && php artisan serve --host=127.0.0.1 --port=8000) &
BACKEND_PID=$!
(cd frontend && npm run dev -- --port 3000) &
FRONTEND_PID=$!
trap 'kill "$BACKEND_PID" "$FRONTEND_PID" 2>/dev/null || true' EXIT INT TERM
printf '\nParkFlow: http://127.0.0.1:3000\nTekan Ctrl+C untuk berhenti.\n'
wait
