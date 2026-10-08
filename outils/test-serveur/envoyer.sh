#!/bin/bash
# Test serveur de Jarvis Jr. : dépose la page de diagnostic sur jr.qyris.fr, le temps du test.
# À lancer par Colin dans son terminal (les envois vers OVH restent entre ses mains) :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/test-serveur/envoyer.sh [VERSION_PHP]
# Exemple : outils/test-serveur/envoyer.sh 8.4  (passe aussi jr.qyris.fr en PHP 8.4)
#
# Envoie :
# - jr.qyris.fr/www/diag-<jeton>.php : diagnostic.php sous un nom aléatoire
# - jr.qyris.fr/www/.htaccess, essai.webp, essai.webmanifest : en-têtes, réécriture, types MIME
# - jr.qyris.fr/serveur/config-test.php : accès à la base, droits 600, hors du web
# - avec VERSION_PHP : un .ovhconfig dans jr.qyris.fr/ et dans jr.qyris.fr/www/
#   (la doc OVH ne dit pas clairement lequel compte pour un multisite)
# nettoyer.sh retire tout, sauf les .ovhconfig et le dossier serveur/.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJET="$(cd "$ICI/../.." && pwd)"
SECRETS="$PROJET/../../secrets/jarvis-jr.env"
SFTP="$PROJET/outils/ovh-sftp.sh"
FILTRE="$HOME/.claude/hooks/redact-secrets"
VERSION="${1:-}"

[ -f "$SECRETS" ] || { echo "Fichier de secrets manquant : $SECRETS"; exit 1; }
[ -x "$FILTRE" ] || { echo "Filtre des secrets introuvable : $FILTRE"; exit 1; }
if [ -n "$VERSION" ] && ! [[ "$VERSION" =~ ^8\.[0-9]$ ]]; then
    echo "Version PHP invalide : $VERSION (exemple : 8.4)"
    exit 1
fi

# Dossier temporaire effacé en sortie quoi qu'il arrive : il contient la config de la base
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/www" "$TMP/serveur"

JETON="$(openssl rand -hex 12)"
cp "$ICI/diagnostic.php" "$TMP/www/diag-$JETON.php"
cp "$PROJET/site/img/fonds/fond-poly.webp" "$TMP/www/essai.webp"
echo '{"name":"essai"}' > "$TMP/www/essai.webmanifest"
cat > "$TMP/www/.htaccess" <<'EOF'
Options -Indexes
Header always set X-Jr-Essai "htaccess-ok"
RewriteEngine On
RewriteRule ^essai-reecriture$ essai.webmanifest [L]
EOF

# Config de la base, écrite par PHP depuis les variables d'environnement (rien sur la ligne de commande)
(
    set -a; source "$SECRETS"; set +a
    umask 077
    php -r 'echo "<?php\n// Généré par envoyer.sh pour le test serveur, retiré par nettoyer.sh\nreturn " . var_export(["host" => getenv("DB_HOST"), "name" => getenv("DB_NAME"), "user" => getenv("DB_USER"), "password" => getenv("DB_PASSWORD")], true) . ";\n";' > "$TMP/serveur/config-test.php"
)

OVHCONFIG=""
if [ -n "$VERSION" ]; then
    cat > "$TMP/www/.ovhconfig" <<EOF
app.engine=php
app.engine.version=$VERSION

http.firewall=none
environment=production

container.image=stable64
EOF
    OVHCONFIG="put '$TMP/www/.ovhconfig' -o 'jr.qyris.fr/.ovhconfig';"
fi

echo "Envoi sur jr.qyris.fr..."
"$SFTP" "mirror --reverse --verbose '$TMP/www/' 'jr.qyris.fr/www/';
mirror --reverse --verbose '$TMP/serveur/' 'jr.qyris.fr/serveur/';
chmod 600 jr.qyris.fr/serveur/config-test.php;
$OVHCONFIG
cls -la jr.qyris.fr jr.qyris.fr/www jr.qyris.fr/serveur" 2>&1 | "$FILTRE"

echo "$JETON" > "$ICI/.jeton"
echo
echo "Fait. Dis à Claude que c'est envoyé (il lit l'adresse dans outils/test-serveur/.jeton)."
if [ -n "$VERSION" ]; then
    echo "PHP $VERSION demandé : OVH peut mettre quelques minutes à l'appliquer."
fi
echo "Après l'analyse : outils/test-serveur/nettoyer.sh"
