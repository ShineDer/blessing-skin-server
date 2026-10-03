#!/bin/sh
set -eu

PUBLIC_ASSETS_DIR="${PUBLIC_ASSETS_DIR:-/opt/public-assets}"

mkdir -p "$PUBLIC_ASSETS_DIR"
find "$PUBLIC_ASSETS_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
cp -a /app/public/. "$PUBLIC_ASSETS_DIR/"
chown -R www-data:www-data "$PUBLIC_ASSETS_DIR"

exec "$@"
