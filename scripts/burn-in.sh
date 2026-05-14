#!/bin/bash
# Run burn-in loop for Feature tests
COUNT=${1:-5}
echo "🔥 Starting burn-in loop ($COUNT iterations)"
for i in $(seq 1 "$COUNT"); do
  echo "Iteration $i/$COUNT"
  DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan test --testsuite=Feature || exit 1
done
