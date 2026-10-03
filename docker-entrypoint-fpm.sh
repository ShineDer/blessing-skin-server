#!/bin/sh
set -eu

PUBLIC_ASSETS_DIR="${PUBLIC_ASSETS_DIR:-/opt/public-assets}"

mkdir -p "$PUBLIC_ASSETS_DIR"
find "$PUBLIC_ASSETS_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
cp -a /app/public/. "$PUBLIC_ASSETS_DIR/"

# Laravel generates locale bundles at runtime; keep them on the shared public volume.
rm -rf /app/public/lang
mkdir -p "$PUBLIC_ASSETS_DIR/lang"
ln -s "$PUBLIC_ASSETS_DIR/lang" /app/public/lang

# Plugin assets live in storage, but only their public assets are exposed.
if [ -d /app/storage/plugins ]; then
    for plugin in /app/storage/plugins/*; do
        [ -d "$plugin/assets" ] || continue
        name=$(basename "$plugin")
        mkdir -p "$PUBLIC_ASSETS_DIR/plugins/$name"
        cp -a "$plugin/assets" "$PUBLIC_ASSETS_DIR/plugins/$name/"
    done
fi

chown -R www-data:www-data "$PUBLIC_ASSETS_DIR"

exec "$@"
