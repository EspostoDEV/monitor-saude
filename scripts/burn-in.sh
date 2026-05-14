#!/bin/bash
# Run burn-in loop for Feature tests
PHP_CMD="php"
if [ -f "./vendor/bin/sail" ] && ./vendor/bin/sail ps | grep "laravel.test" | grep -qE "Up|running"; then
  PHP_CMD="./vendor/bin/sail php"
elif ! command -v php &> /dev/null; then
  echo "❌ Error: 'php' not found and Sail is not running."
  exit 1
fi

COUNT=${1:-5}
if ! [[ "$COUNT" =~ ^[0-9]+$ ]]; then
  echo "❌ Error: COUNT must be a number."
  exit 1
fi

echo "🔥 Starting burn-in loop ($COUNT iterations)"
for i in $(seq 1 "$COUNT"); do
  echo "Iteration $i/$COUNT"
  DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite $PHP_CMD artisan test --testsuite=Feature || exit 1
done
