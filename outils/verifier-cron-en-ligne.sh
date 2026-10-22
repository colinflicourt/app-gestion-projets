#!/bin/bash
# Vérifie en ligne que Apache d'OVH transmet l'en-tête Authorization au planificateur de Jarvis Jr.
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/verifier-cron-en-ligne.sh
# Attendu : bon jeton -> 200, mauvais jeton -> 404, sans jeton -> 404.
# Le jeton est lu dans secrets/jarvis-jr.env (CRON_JETON) et passe par un fichier d'en-têtes temporaire :
# jamais affiché, jamais dans la commande ni dans l'historique du terminal.
# Le bon jeton déclenche un vrai passage du planificateur (sans danger : les envois ne partent jamais en double).

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SECRETS="$ICI/../../../secrets/jarvis-jr.env"
URL="${JR_URL:-https://jr.qyris.fr}/api/cron"
[ -f "$SECRETS" ] || { echo "Fichier de secrets manquant : $SECRETS"; exit 1; }

BON="$(mktemp)"
MAUVAIS="$(mktemp)"
trap 'rm -f "$BON" "$MAUVAIS"' EXIT
chmod 600 "$BON" "$MAUVAIS"
php -r 'require $argv[1]; $e = lire_env($argv[2]); $j = $e["CRON_JETON"] ?? ""; if ($j === "") { fwrite(STDERR, "CRON_JETON absent\n"); exit(1); } echo "Authorization: Bearer ", $j, "\n";' "$ICI/lib-env.php" "$SECRETS" > "$BON"
echo "Authorization: Bearer mauvais-jeton-pour-l-essai" > "$MAUVAIS"

appel() {  # $1 : fichier d'en-têtes, ou rien
    if [ -n "${1:-}" ]; then
        curl -sS --max-time 30 -o /dev/null -w "%{http_code}" -X POST -H @"$1" "$URL"
    else
        curl -sS --max-time 30 -o /dev/null -w "%{http_code}" -X POST "$URL"
    fi
}

BON_CODE="$(appel "$BON")"
MAUVAIS_CODE="$(appel "$MAUVAIS")"
SANS_CODE="$(appel)"
echo "Bon jeton      : $BON_CODE (attendu 200)"
echo "Mauvais jeton  : $MAUVAIS_CODE (attendu 404)"
echo "Sans jeton     : $SANS_CODE (attendu 404)"

if [ "$BON_CODE" = 200 ] && [ "$MAUVAIS_CODE" = 404 ] && [ "$SANS_CODE" = 404 ]; then
    echo "OK : Apache transmet bien Authorization."
else
    echo "Pas bon. Si le bon jeton donne 404 : l'en-tête n'arrive pas à PHP (règle du .htaccess), préviens Claude."
    exit 1
fi
