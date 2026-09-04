#!/bin/sh
set -e

# 1. Warm up internal storage directories to prevent permission crashes
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# 2. Optimize Laravel by compiling environment variables and code paths into RAM
echo "Caching Laravel configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 3. Hand control over to the container's primary command (e.g., php-fpm)
echo "Laravel is optimized. Starting application backend..."
exec "$@"
