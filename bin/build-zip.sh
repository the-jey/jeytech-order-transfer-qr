#!/usr/bin/env bash
# Construit dist/jeytech-order-transfer-qr.zip (fichiers exclus : voir .distignore).
set -euo pipefail
cd "$(dirname "$0")/.."

slug="jeytech-order-transfer-qr"
version="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$slug.php" | awk '{print $NF}')"
constant="$(grep -m1 "JEYTECH_OTQR_VERSION'," "$slug.php" | sed -E "s/.*'([0-9.]+)'.*/\1/")"
stable="$(grep -m1 '^Stable tag:' readme.txt | awk '{print $NF}')"

if [[ "$version" != "$stable" || "$version" != "$constant" ]]; then
	echo "✗ Versions incohérentes : en-tête $version, constante $constant, Stable tag $stable" >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/$slug"
rsync -a --exclude-from=.distignore ./ "dist/$slug/"
find "dist/$slug" -type d -exec chmod 755 {} +
find "dist/$slug" -type f -exec chmod 644 {} +
(cd dist && zip -qr "$slug.zip" "$slug")

echo "✓ dist/$slug.zip — version $version"
unzip -l "dist/$slug.zip"
