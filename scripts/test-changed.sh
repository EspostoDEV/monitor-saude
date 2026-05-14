#!/bin/bash
# Run tests for changed files only (using git diff)
CHANGED_FILES=$(git diff --name-only HEAD | grep -E 'tests/.*Test.php')
if [ -z "$CHANGED_FILES" ]; then
  echo "No test files changed."
  exit 0
fi
php artisan test $CHANGED_FILES
