#!/usr/bin/env bash
# Guarded MySQL 8 verification. Refuses to run against anything but a dedicated
# database whose name ends in "_test". Never run against a production database.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f .env.testing ]]; then
  echo "[ERROR] .env.testing not found."
  echo "Create .env.testing with the dedicated MySQL test credentials (see docs/MYSQL-VERIFICATION.md)."
  exit 2
fi

TEST_DB="$(sed -n 's/^DB_DATABASE=//p' .env.testing | head -1 | tr -d '"')"
if [[ -z "$TEST_DB" ]]; then
  echo "[ERROR] DB_DATABASE is missing."
  exit 3
fi

if [[ "$TEST_DB" != *_test ]]; then
  echo "[REFUSED] DB_DATABASE must end in _test. Current value: $TEST_DB"
  echo "migrate:fresh was NOT executed."
  exit 4
fi

case "$TEST_DB" in
  mysql|production|gym_saas)
    echo "[REFUSED] Suspicious database name."
    exit 5
    ;;
esac

php artisan optimize:clear --env=testing || exit 10
php artisan migrate:fresh --seed --env=testing || exit 11
# Run PHPUnit directly: the collision `artisan test` command always injects its
# own discovered config, so a custom --configuration cannot be passed through it.
# phpunit.mysql.xml sets APP_ENV=testing, which loads .env.testing (MySQL).
php vendor/bin/phpunit --configuration=phpunit.mysql.xml || exit 12
echo "[PASS] MySQL migration, seed and full test suite passed on $TEST_DB."
