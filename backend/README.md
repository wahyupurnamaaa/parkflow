# ParkFlow API

Laravel REST API untuk ParkFlow. Lihat [README utama](../README.md), [API](../docs/API.md), dan [arsitektur](../docs/ARCHITECTURE.md).

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Jangan menyalin ulang `.env` atau mengganti APP_KEY pada instalasi yang telah digunakan. Gunakan script setup utama untuk setup yang idempotent.

```bash
php artisan test
vendor/bin/pint --test
```
