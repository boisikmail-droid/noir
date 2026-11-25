#!/bin/sh
set -e
cd /app
if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi
exec "$@"
