#!/usr/bin/env bash
# Run queue worker and scheduler using the TEST database (payment_app_test).
# Use this when you want to test payroll/payments E2E against the test DB
# (e.g. run migrations on test DB, then start this so worker/scheduler use it).
#
# Usage:
#   Terminal 1: ./run-queue-scheduler-test-db.sh queue   # or: php artisan queue:work ... with test DB
#   Terminal 2: ./run-queue-scheduler-test-db.sh scheduler
#   Or run both in background (see below).
#
# Ensure test DB exists and migrations are run:
#   php artisan migrate --database=pgsql --env=testing  # or use DB_DATABASE=payment_app_test

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

export APP_ENV=testing
export DB_CONNECTION=pgsql
export DB_DATABASE=payment_app_test
# Optional: use same .env as app but override DB
if [ -f .env ]; then
  set -a
  source .env
  set +a
fi
export APP_ENV=testing
export DB_DATABASE=payment_app_test

case "${1:-}" in
  queue)
    exec php artisan queue:work database --queue=high,default --tries=1
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  *)
    echo "Usage: $0 {queue|scheduler}"
    echo ""
    echo "  queue     - Run queue worker (test DB)"
    echo "  scheduler - Run scheduler (test DB)"
    echo ""
    echo "Run in two terminals for E2E testing against payment_app_test."
    exit 1
    ;;
esac
