#!/usr/bin/env bash
set -Eeuo pipefail
STAMP=$(date +%Y%m%d_%H%M%S)
OUT=${1:-$PWD/backups/migration_$STAMP}
mkdir -p "$OUT"
DB_ID=$(docker ps -q --filter label=com.docker.compose.project=sadino --filter label=com.docker.compose.service=db | head -1)
APP_ID=$(docker ps -q --filter label=com.docker.compose.project=sadino --filter label=com.docker.compose.service=app | head -1)
[[ -n "$DB_ID" ]] || { echo "SADINO db container aktif tidak ditemukan." >&2; exit 1; }
DB_NAME=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_DATABASE=//p' | head -1)
DB_USER=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_USER=//p' | head -1)
DB_PASS=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_PASSWORD=//p' | head -1)
docker exec "$DB_ID" mariadb-dump -u"$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" | gzip -9 > "$OUT/sadino.sql.gz"
if [[ -n "$APP_ID" ]]; then
  docker cp "$APP_ID":/var/www/html/storage/uploads "$OUT/uploads" || true
  docker cp "$APP_ID":/var/www/html/storage/logs "$OUT/logs" || true
fi
sha256sum "$OUT/sadino.sql.gz" > "$OUT/SHA256SUMS"
echo "Backup selesai: $OUT"
