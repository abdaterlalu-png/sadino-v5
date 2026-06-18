#!/usr/bin/env bash
set -Eeuo pipefail
cd /var/www/html
mkdir -p storage/uploads storage/monitor storage/sessions storage/logs backups
chown -R www-data:www-data storage || true
chmod -R 0750 storage || true

for i in $(seq 1 90); do
  if php -r 'require "app/bootstrap.php"; db()->query("SELECT 1");' >/dev/null 2>&1; then
    break
  fi
  if [[ "$i" == "90" ]]; then
    echo "[FATAL] Database tidak dapat diakses setelah 180 detik." >&2
    exit 1
  fi
  sleep 2
done

php cli/migrate.php
if [[ "${SEED_DEMO_DATA:-true}" == "true" ]]; then
  php cli/seed_demo.php
fi

if [[ -n "${CREATOR_PASSWORD:-}" ]]; then
  php cli/create_creator.php
fi

exec "$@"
