#!/bin/bash
# Exécute des commandes lftp sur l'hébergement OVH perso, en SFTP (compte principal, port 22).
# - Mot de passe passé par la variable LFTP_PASSWORD, jamais sur la ligne de commande
# - Une seule tentative de connexion et un seul essai de mot de passe : une boucle de
#   reconnexion lftp a fait bloquer le Mac par OVH le 28/09
# - Chemins relatifs au dossier personnel de l'hébergement (ex. jr.qyris.fr/www)
#
# Toujours lancer à travers le filtre des secrets :
#   { outils/ovh-sftp.sh 'cls -la jr.qyris.fr' ; } 2>&1 | ~/.claude/hooks/redact-secrets

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../../../secrets/ovh-hosting.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Fichier de secrets manquant : $ENV_FILE"
    exit 1
fi
if [ $# -ne 1 ]; then
    echo "Usage : $0 'commandes lftp'"
    exit 1
fi

# Variables dans ovh-hosting.env : FTP_HOST, FTP_LOGIN, FTP_PASSWORD (les mêmes servent au SFTP)
source "$ENV_FILE"
export LFTP_PASSWORD="$FTP_PASSWORD"

lftp -c "
set net:max-retries 1;
set net:timeout 20;
set cmd:fail-exit yes;
set sftp:auto-confirm yes;
set sftp:connect-program 'ssh -a -x -o PreferredAuthentications=password,keyboard-interactive -o NumberOfPasswordPrompts=1';
open --env-password -u '$FTP_LOGIN' 'sftp://$FTP_HOST';
$1
"
