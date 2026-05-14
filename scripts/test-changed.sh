#!/bin/bash
# Run tests for changed files only (using git diff)
PHP_CMD="php"
if [ -f "./vendor/bin/sail" ] && ./vendor/bin/sail ps | grep "laravel.test" | grep -qE "Up|running"; then
  PHP_CMD="./vendor/bin/sail php"
elif ! command -v php &> /dev/null; then
  echo "❌ Error: 'php' not found and Sail is not running."
  exit 1
fi

CHANGED_FILES=$(git diff --name-only HEAD | grep -E 'tests/.*Test\.php$')
if [ -z "$CHANGED_FILES" ]; then
  echo "No test files changed."
  exit 0
fi
$PHP_CMD artisan test $CHANGED_FILES
