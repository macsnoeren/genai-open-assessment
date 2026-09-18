#!/bin/sh
set -e

DB_DIR="/var/www/html/database"
DB_FILE="$DB_DIR/database.sqlite"

mkdir -p "$DB_DIR"

if [ ! -f "$DB_FILE" ]; then
    echo "Geen database gevonden, database wordt geïnitialiseerd..."
    php /var/www/html/htdocs/setup/init_db.php
fi

chown -R www-data:www-data "$DB_DIR"

exec "$@"
