#!/bin/bash

php artisan down

git pull

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci

npm run build

php artisan optimize:clear

php artisan migrate --force

php artisan optimize

php artisan queue:restart

php artisan schedule:interrupt

php artisan up
