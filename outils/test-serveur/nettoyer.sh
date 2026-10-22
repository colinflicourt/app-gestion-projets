#!/bin/bash
# Retire de jr.qyris.fr les fichiers d'essai déposés par envoyer.sh.
# Après un envoi --lot2 : retire seulement le diagnostic (le .htaccess de l'app reste).
# Garde le dossier serveur/ et les .ovhconfig (ils serviront à l'app).
# À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/test-serveur/nettoyer.sh

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SFTP="$ICI/../ovh-sftp.sh"
FILTRE="$HOME/.claude/hooks/redact-secrets"

[ -f "$ICI/.jeton" ] || { echo "Pas de .jeton : rien à nettoyer (ou déjà fait)."; exit 1; }
[ -x "$FILTRE" ] || { echo "Filtre des secrets introuvable : $FILTRE"; exit 1; }
JETON="$(cat "$ICI/.jeton")"
[[ "$JETON" =~ ^[0-9a-f]{24}$ ]] || { echo "Jeton illisible dans .jeton"; exit 1; }

echo "Nettoyage de jr.qyris.fr..."
if [ -f "$ICI/.mode-lot2" ]; then
    "$SFTP" "rm 'jr.qyris.fr/www/diag-$JETON.php';
cls -la jr.qyris.fr/www" 2>&1 | "$FILTRE"
    rm "$ICI/.mode-lot2"
else
"$SFTP" "rm 'jr.qyris.fr/www/diag-$JETON.php' jr.qyris.fr/www/.htaccess jr.qyris.fr/www/essai.webp jr.qyris.fr/www/essai.webmanifest jr.qyris.fr/serveur/config-test.php;
cls -la jr.qyris.fr jr.qyris.fr/www jr.qyris.fr/serveur" 2>&1 | "$FILTRE"
fi

rm "$ICI/.jeton"
echo
echo "Fait. Dis à Claude que c'est nettoyé."
