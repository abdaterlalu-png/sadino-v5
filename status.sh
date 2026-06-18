#!/usr/bin/env bash
set -Eeuo pipefail
echo "SADINO V5 STATUS"
docker ps --filter label=com.dndjava.project=sadino --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
APP_ID=$(docker ps -q --filter label=com.dndjava.project=sadino --filter label=com.dndjava.role=application | head -1)
DB_ID=$(docker ps -q --filter label=com.dndjava.project=sadino --filter label=com.dndjava.role=database | head -1)
[[ -n "$APP_ID" ]] && docker exec "$APP_ID" curl -fsS http://127.0.0.1/health.php && echo
[[ -n "$DB_ID" ]] && docker inspect "$DB_ID" --format 'DB health: {{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}'
