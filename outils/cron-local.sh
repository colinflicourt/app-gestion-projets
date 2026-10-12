#!/bin/bash
# Appelle le planificateur de l'aperçu local (http://localhost:8787), comme cron-job.org le fera en ligne.
#   outils/cron-local.sh            un passage
#   outils/cron-local.sh --boucle   un passage par minute, jusqu'à Ctrl+C
# Le jeton est lu dans serveur/config.php et passe par un fichier d'en-têtes : jamais affiché ni en argument.
# Claude le lance à travers le filtre : { outils/cron-local.sh ; } 2>&1 | ~/.claude/hooks/redact-secrets

set -euo pipefail
ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENTETES="$(mktemp)"
trap 'rm -f "$ENTETES"' EXIT
php -r '$c = require $argv[1]; echo "Authorization: Bearer ", $c["cron"]["jeton"] ?? "", "\n";' "$ICI/../serveur/config.php" > "$ENTETES"

passage() {
    curl -sS --max-time 60 -o /dev/null -w "%{http_code}  $(date +%H:%M:%S)\n" -X POST -H @"$ENTETES" http://localhost:8787/api/cron
}

passage
while [ "${1:-}" = "--boucle" ]; do
    sleep 60
    passage
done
