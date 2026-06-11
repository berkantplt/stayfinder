#!/usr/bin/env bash
#
# Production deploy script — sunucuda çalışır.
# GitHub Actions her "git push" sonrası bu dosyayı SSH ile çalıştırır.
# Elle de çalıştırılabilir:  bash deploy.sh
#
set -euo pipefail

echo "==> [1/8] Bakım moduna alınıyor"
php artisan down --retry=15 || true

echo "==> [2/8] Kod çekiliyor (git pull)"
git pull origin main

echo "==> [3/8] PHP bağımlılıkları (composer)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> [4/8] Frontend build (vite)"
npm ci
npm run build

echo "==> [5/8] Veritabanı migration"
php artisan migrate --force

echo "==> [6/8] Cache yenileniyor"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> [7/8] Queue worker yeniden başlatılıyor"
php artisan queue:restart

echo "==> [8/8] Bakım modundan çıkılıyor"
php artisan up

echo ""
echo "==> Deploy tamamlandı ✅"
