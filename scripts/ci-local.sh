#!/bin/bash
# Mirror CI environment locally using SQLite
if [ ! -f .env.testing ]; then
  echo "⚠️ .env.testing not found. Copying from .env.example..."
  cp .env.example .env.testing
fi
mkdir -p database
touch database/database.sqlite
php artisan key:generate --env=testing
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan test
