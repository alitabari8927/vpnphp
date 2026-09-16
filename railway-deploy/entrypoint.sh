#!/bin/sh
set -e

# Railway Volumes are mounted at /data (set the volume's mount path to /data
# in the Railway dashboard). Everything the panel needs to survive a
# redeploy (config.php, storage/, uploads/ads/) is symlinked into /data.

DATA_DIR="/data"
APP_DIR="/var/www/html"

mkdir -p "$DATA_DIR/storage" "$DATA_DIR/uploads-ads"

# Seed persistent dirs on first boot only.
if [ -z "$(ls -A "$DATA_DIR/storage" 2>/dev/null)" ]; then
    cp -a /opt/defaults/storage/. "$DATA_DIR/storage/"
fi
if [ -z "$(ls -A "$DATA_DIR/uploads-ads" 2>/dev/null)" ]; then
    cp -a /opt/defaults/uploads-ads/. "$DATA_DIR/uploads-ads/"
fi

# Replace the in-image copies with symlinks into the volume.
rm -rf "$APP_DIR/storage" "$APP_DIR/uploads/ads"
ln -sfn "$DATA_DIR/storage" "$APP_DIR/storage"
ln -sfn "$DATA_DIR/uploads-ads" "$APP_DIR/uploads/ads"

# config.php doesn't exist until you run /install/. Point the path at the
# volume BEFORE install runs, so the installer's file_put_contents() call
# writes straight into persistent storage.
ln -sfn "$DATA_DIR/config.php" "$APP_DIR/config.php"

chown -R www-data:www-data "$DATA_DIR" "$APP_DIR/config.php" 2>/dev/null || true

# Railway injects $PORT; Apache must listen on it instead of the default 80.
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

exec "$@"
