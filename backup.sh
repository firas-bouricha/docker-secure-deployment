#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="backup/td2.sql"
mkdir -p backup

# 1. Export : le mot de passe est lu dans le conteneur, jamais dans la commande
docker compose exec -T db sh -c \
  'MYSQL_PWD=$(cat /run/secrets/db_app_password) mariadb-dump -u td2app td2' \
  > "$BACKUP_FILE"

# 2. Vérification
test -s "$BACKUP_FILE"                                   # fichier non vide
tail -n 1 "$BACKUP_FILE" | grep -q "Dump completed"      # dump complet
! grep -qF -f secrets/db_app_password "$BACKUP_FILE"     # mot de passe absent

echo "Sauvegarde OK : $BACKUP_FILE"
