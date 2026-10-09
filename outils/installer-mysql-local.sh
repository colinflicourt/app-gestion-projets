#!/bin/bash
# Installe MySQL 8.4 en local (Homebrew) pour le développement de Jarvis Jr. : bases jarvisjr_dev et
# jarvisjr_test, utilisateur au nom aléatoire, accès dans secrets/jarvis-jr-local.env (droits 600).
# Le nom est aléatoire pour que le garde-fou des secrets ne le confonde pas avec celui des bases.
# MySQL n'est pas lancé au démarrage du Mac : « brew services run » le lance pour la session,
# « brew services stop mysql@8.4 » l'arrête.
# À lancer à travers le filtre des secrets.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_LOCAL="$ICI/../../../secrets/jarvis-jr-local.env"

brew list mysql@8.4 >/dev/null 2>&1 || brew install mysql@8.4
MYSQL_BIN="$(brew --prefix mysql@8.4)/bin"
brew services run mysql@8.4
"$MYSQL_BIN/mysqladmin" --wait=30 --connect-timeout=2 -uroot ping

if [ -f "$ENV_LOCAL" ]; then
    echo "Déjà configuré : $ENV_LOCAL existe."
    exit 0
fi

UTILISATEUR="jr_$(openssl rand -hex 6)"
MOT_DE_PASSE="$(openssl rand -hex 16)"
# Le mot de passe passe par l'entrée standard de mysql, jamais en argument
"$MYSQL_BIN/mysql" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS jarvisjr_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS jarvisjr_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS '$UTILISATEUR'@'localhost' IDENTIFIED BY '$MOT_DE_PASSE';
GRANT ALL PRIVILEGES ON jarvisjr_dev.* TO '$UTILISATEUR'@'localhost';
GRANT ALL PRIVILEGES ON jarvisjr_test.* TO '$UTILISATEUR'@'localhost';
SQL

umask 077
cat > "$ENV_LOCAL" <<EOF
# Base MySQL locale de Jarvis Jr. (outils/installer-mysql-local.sh). Données d'essai seulement.
DB_HOST=localhost
DB_NAME=jarvisjr_dev
DB_NAME_TEST=jarvisjr_test
DB_USER=$UTILISATEUR
DB_PASSWORD=$MOT_DE_PASSE
EOF
echo "MySQL local prêt. Accès écrits dans secrets/jarvis-jr-local.env."
