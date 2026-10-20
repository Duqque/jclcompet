#!/usr/bin/env bash
# Restauration : bin/restore.sh storage/backups/base-AAAAMMJJ-HHMMSS.sql.gz [storage/backups/documents-AAAAMMJJ-HHMMSS.tar.gz]
# ATTENTION : remplace le contenu actuel de la base. Faites une sauvegarde avant.
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f "${1:-}" ] || { echo "Fichier de sauvegarde introuvable."; exit 1; }
get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
read -r -p "Restaurer $(basename "$1") dans la base $(get DB_NAME) ? Taper « oui » : " ok
[ "$ok" = "oui" ] || { echo "Annulé."; exit 1; }
export MYSQL_PWD="$(get DB_PASSWORD)"
gunzip -c "$1" | mysql --default-character-set=utf8mb4 -h "$(get DB_HOST)" -P "$(get DB_PORT || echo 3306)" -u "$(get DB_USER)" "$(get DB_NAME)"
if [ -n "${2:-}" ]; then tar -xzf "$2" -C storage; fi
echo "Restauration terminée."
