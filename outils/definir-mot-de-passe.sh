#!/bin/bash
# Choisir le mot de passe de Jarvis Jr. Seule son empreinte bcrypt est gardée, dans secrets/jarvis-jr.env
# (APP_MOT_DE_PASSE_EMPREINTE). Crée aussi la clé de chiffrement (APP_CLE_CHIFFREMENT) si elle manque.
# À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/definir-mot-de-passe.sh
# Après un changement, relancer outils/deploy-ovh.sh pour l'appliquer en ligne.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FICHIER="${JR_ENV_FICHIER:-$ICI/../../../secrets/jarvis-jr.env}"
[ -f "$ENV_FICHIER" ] || { echo "Fichier de secrets introuvable : $ENV_FICHIER"; exit 1; }

echo "Choisis une phrase longue (12 caractères au moins) : tu ne la taperas qu'une fois tous les 90 jours environ."
# IFS= et -r : les espaces au début et à la fin, et les antislashs, sont gardés tels quels
IFS= read -r -s -p "Mot de passe : " MDP1; echo
IFS= read -r -s -p "Encore une fois : " MDP2; echo
[ "$MDP1" = "$MDP2" ] || { echo "Les deux saisies diffèrent. Rien n'a changé."; exit 1; }
[ "${#MDP1}" -ge 12 ] || { echo "Trop court : 12 caractères au moins. Rien n'a changé."; exit 1; }

EMPREINTE="$(printf '%s' "$MDP1" | php "$ICI/empreinte.php")"
unset MDP1 MDP2
JR_VALEUR="$EMPREINTE" php "$ICI/env-definir.php" "$ENV_FICHIER" APP_MOT_DE_PASSE_EMPREINTE

if ! grep -q '^APP_CLE_CHIFFREMENT=' "$ENV_FICHIER"; then
    JR_VALEUR="$(php -r 'echo base64_encode(sodium_crypto_secretbox_keygen());')" php "$ICI/env-definir.php" "$ENV_FICHIER" APP_CLE_CHIFFREMENT
    echo "Clé de chiffrement créée."
fi
echo "Fait. Empreinte enregistrée (le mot de passe lui-même n'est gardé nulle part)."
