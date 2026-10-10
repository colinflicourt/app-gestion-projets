#!/bin/bash
# Mise en ligne de Jarvis Jr. sur jr.qyris.fr. À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/deploy-ovh.sh
# 1. tests  2. config de production  3. simulation  4. confirmation  5. envoi  6. vérification
# Garde en ligne : www/.ovhconfig, serveur/data/ (journal). config.php et trello-ids.json sont posés à part.

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

EXCLUS_SITE="--exclude-glob .ovhconfig --exclude-glob .DS_Store"
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

echo "5/6 Envoi (le serveur et sa configuration d'abord, le site ensuite)"
"$SFTP" "$(miroir_serveur '')
put '$TMP/config.php' -o 'jr.qyris.fr/serveur/config.php';
chmod 600 jr.qyris.fr/serveur/config.php;
put '$TMP/trello-ids.json' -o 'jr.qyris.fr/serveur/trello-ids.json';
$(miroir_site '')" 2>&1 | "$FILTRE"

echo "6/6 Vérification"
SANTE="$(curl -sS --max-time 20 https://jr.qyris.fr/api/sante 2>&1 | "$FILTRE" || true)"
echo "$SANTE"
if ! printf '%s' "$SANTE" | grep -q '"base_ok":true'; then
    echo "Échec : /api/sante ne répond pas « base_ok »: true. Le site est peut-être cassé, préviens Claude."
    exit 1
fi
echo "En ligne."
