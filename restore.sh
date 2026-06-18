#!/usr/bin/env bash
set -Eeuo pipefail
BACKUP=${1:-}
[[ -n "$BACKUP" && -f "$BACKUP/sadino.sql.gz" ]] || { echo "Usage: bash restore.sh /path/backup-folder" >&2; exit 1; }
DB_ID=$(docker ps -q --filter label=com.dndjava.project=sadino --filter label=com.dndjava.role=database | head -1)
APP_ID=$(docker ps -q --filter label=com.dndjava.project=sadino --filter label=com.dndjava.role=application | head -1)
[[ -n "$DB_ID" ]] || { echo "[FAIL] Database SADINO tidak running." >&2; exit 1; }
DB_NAME=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_DATABASE=//p' | head -1)
DB_USER=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_USER=//p' | head -1)
DB_PASS=$(docker inspect "$DB_ID" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^MARIADB_PASSWORD=//p' | head -1)
read -r -p "Ketik RESTORE-SADINO untuk melanjutkan: " C
[[ "$C" == "RESTORE-SADINO" ]] || exit 1
gunzip -c "$BACKUP/sadino.sql.gz" | docker exec -i "$DB_ID" mariadb -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
if [[ -n "$APP_ID" && -d "$BACKUP/uploads" ]]; then
  docker cp "$BACKUP/uploads/." "$APP_ID":/var/www/html/storage/uploads/
fi
echo "[PASS] Restore database selesai. Restart app melalui Hostinger Docker Manager."
