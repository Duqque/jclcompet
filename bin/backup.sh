#!/usr/bin/env bash
# Sauvegarde : base MySQL (mysqldump) + documents téléversés (storage/documents).
# Usage : bin/backup.sh [dossier_de_destination]   (lit les accès dans .env)
set -euo pipefail
cd "$(dirname "$0")/.."
DEST="${1:-storage/backups}"
mkdir -p "$DEST"
get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
STAMP=$(date +%Y%m%d-%H%M%S)
export MYSQL_PWD="$(get DB_PASSWORD)"
mysqldump --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 \
  -h "$(get DB_HOST)" -P "$(get DB_PORT || echo 3306)" -u "$(get DB_USER)" "$(get DB_NAME)" | gzip > "$DEST/base-$STAMP.sql.gz"
tar -czf "$DEST/documents-$STAMP.tar.gz" -C storage documents
echo "Sauvegarde créée : $DEST/base-$STAMP.sql.gz et $DEST/documents-$STAMP.tar.gz"
