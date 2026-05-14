#!/bin/bash
# Mirror CI environment locally using SQLite
mkdir -p database
touch database/database.sqlite
php artisan key:generate --env=testing
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan test
