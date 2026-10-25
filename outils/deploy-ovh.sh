#!/bin/bash
# Mise en ligne de Jarvis Jr. sur jr.qyris.fr. À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/deploy-ovh.sh
# Les lots 2 et 3 partent avec : serveur/lib, routes, migrations (003 à 006), cron-ovh.php et site/. Les migrations
# s'appliquent seules au premier appel de l'API (assurer_migrations), donc à la vérification de l'étape 6.
# 1. tests  2. config de production  3. simulation  4. confirmation  5. envoi  6. vérification
# Garde en ligne : www/.ovhconfig, serveur/data/ (journal). config.php et trello-ids.json sont posés à part.
# sw.js part en dernier : un iPhone qui s'ouvre pendant l'envoi ne met jamais en cache un mélange de fichiers.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJET="$(cd "$ICI/.." && pwd)"
SFTP="$ICI/ovh-sftp.sh"
FILTRE="$HOME/.claude/hooks/redact-secrets"
[ -x "$FILTRE" ] || { echo "Filtre des secrets introuvable : $FILTRE"; exit 1; }

echo "1/6 Tests"
if ! php "$PROJET/tests/run.php" 2>&1 | "$FILTRE"; then
    echo "Tests en échec : rien n'est envoyé. (MySQL local lancé ? brew services run mysql@8.4)"
    exit 1
fi
if ! (cd "$PROJET" && node --test 'tests/js/*.test.mjs') 2>&1 | "$FILTRE"; then
    echo "Tests des modules de l'app en échec : rien n'est envoyé."
    exit 1
fi

echo "2/6 Configuration de production"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
php "$ICI/generer-config.php" --mode=production --dossier="$TMP" 2>&1 | "$FILTRE"

EXCLUS_SITE="--exclude-glob .ovhconfig --exclude-glob .DS_Store --exclude-glob sw.js"
EXCLUS_SERVEUR="--exclude-glob config.php --exclude-glob trello-ids.json --exclude-glob data/ --exclude-glob .DS_Store"
miroir_serveur() {  # $1 : --dry-run, ou rien
    echo "mirror --reverse --delete --verbose $1 $EXCLUS_SERVEUR '$PROJET/serveur/' 'jr.qyris.fr/serveur/';"
}
miroir_site() {  # $1 : --dry-run, ou rien
    echo "mirror --reverse --delete --verbose $1 $EXCLUS_SITE '$PROJET/site/' 'jr.qyris.fr/www/';"
}

echo "3/6 Simulation (rien n'est modifié)"
"$SFTP" "$(miroir_serveur --dry-run)
$(miroir_site --dry-run)" 2>&1 | "$FILTRE"
echo
read -r -p "4/6 Envoyer ? (o/N) " REPONSE
[ "$REPONSE" = "o" ] || { echo "Rien n'a été envoyé."; exit 0; }

echo "5/6 Envoi (le serveur et sa configuration d'abord, le site ensuite, sw.js en dernier)"
"$SFTP" "$(miroir_serveur '')
put '$TMP/config.php' -o 'jr.qyris.fr/serveur/config.php';
chmod 600 jr.qyris.fr/serveur/config.php;
cls -l jr.qyris.fr/serveur/cron-ovh.php jr.qyris.fr/serveur/migrations;
put '$TMP/trello-ids.json' -o 'jr.qyris.fr/serveur/trello-ids.json';
$(miroir_site '')
put '$PROJET/site/sw.js' -o 'jr.qyris.fr/www/sw.js';" 2>&1 | "$FILTRE"

echo "6/6 Vérification"
SANTE="$(curl -sS --max-time 20 https://jr.qyris.fr/api/sante 2>&1 | "$FILTRE" || true)"
echo "$SANTE"
if ! printf '%s' "$SANTE" | grep -q '"base_ok":true'; then
    echo "Échec : /api/sante ne répond pas « base_ok »: true. Le site est peut-être cassé, préviens Claude."
    exit 1
fi
# version_base lue dans la réponse, comparée au plus grand numéro de migration (NNN_nom.sql)
ATTENDU="$(ls "$PROJET/serveur/migrations/" | grep -E '^[0-9]+_.*\.sql$' | sed -E 's/_.*//' | sort -n | tail -n 1)"
ATTENDU=$((10#$ATTENDU))
VB="$(printf '%s' "$SANTE" | grep -o '"version_base":[0-9]*' | grep -o '[0-9]*$' | head -n 1 || true)"
if [ -z "$VB" ]; then X="illisible"; else X=$((10#$VB)); fi
if [ "$X" = "$ATTENDU" ]; then
    echo "En ligne."
else
    echo "Attention : version_base vaut $X, attendu $ATTENDU. Ne pas considérer la mise en ligne comme finie."
    exit 1
fi
