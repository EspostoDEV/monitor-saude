#!/bin/bash
# Helper to run PHP commands (Sail or local)
PHP_CMD="php"
if [ -f "./vendor/bin/sail" ] && ./vendor/bin/sail ps | grep "laravel.test" | grep -qE "Up|running"; then
  PHP_CMD="./vendor/bin/sail php"
elif ! command -v php &> /dev/null; then
  echo "❌ Error: 'php' not found and Sail is not running."
  echo "Please start Sail with './vendor/bin/sail up' or install PHP locally."
  exit 1
fi

if [ ! -f .env.testing ]; then
  if [ -f .env.example ]; then
    echo "⚠️ .env.testing not found. Copying from .env.example..."
    cp .env.example .env.testing
  else
    echo "❌ Error: .env.example not found. Cannot create .env.testing."
    exit 1
  fi
fi
mkdir -p database
touch database/database.sqlite
# Ensure an APP_KEY exists for testing if not already set
if ! grep -q "APP_KEY=base64" .env.testing; then
  $PHP_CMD artisan key:generate --env=testing
fi
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite $PHP_CMD artisan test
