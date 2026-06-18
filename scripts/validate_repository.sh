#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$ROOT"
for f in app/*.php cli/*.php public/*.php; do php -l "$f" >/dev/null; done
node --check public/assets/js/app.js >/dev/null
python3 - <<'PY'
import yaml
for f in ['docker-compose.yml','docker-compose.domain.example.yml']:
    with open(f,encoding='utf-8') as h: yaml.safe_load(h)
print('YAML OK')
PY
[[ -s templates/SADINO_MONTHLY_UPLOAD_TEMPLATE.xlsx ]]
echo "Repository validation PASS"
