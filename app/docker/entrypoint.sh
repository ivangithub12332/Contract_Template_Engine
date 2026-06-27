#!/usr/bin/env bash
set -e

cd /var/www/app

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --ansi
fi

until php artisan db:show >/dev/null 2>&1; do
    echo "Waiting for the database..."
    sleep 2
done

php artisan migrate --force

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

exec php artisan serve --host=0.0.0.0 --port=8000
