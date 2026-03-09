#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/modern-file-manager.php"
PLUGIN_SLUG="$(basename "$ROOT_DIR")"
DIST_DIR="$ROOT_DIR/dist"
DISTIGNORE_FILE="$ROOT_DIR/.distignore"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Error: plugin file not found at $PLUGIN_FILE" >&2
  exit 1
fi

VERSION="$(awk -F': ' '/^ \* Version: / {print $2; exit}' "$PLUGIN_FILE" | tr -d '\r')"
if [[ -z "$VERSION" ]]; then
  echo "Error: unable to read plugin version from $PLUGIN_FILE" >&2
  exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

mkdir -p "$DIST_DIR"

STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${PLUGIN_SLUG}-build-XXXXXX")"
PACKAGE_DIR="$STAGE_DIR/$PLUGIN_SLUG"
mkdir -p "$PACKAGE_DIR"

if [[ -f "$DISTIGNORE_FILE" ]]; then
  rsync -a "$ROOT_DIR/" "$PACKAGE_DIR/" --exclude-from="$DISTIGNORE_FILE"
else
  rsync -a "$ROOT_DIR/" "$PACKAGE_DIR/"
fi

rm -f "$ZIP_PATH"
(
  cd "$STAGE_DIR"
  zip -rq "$ZIP_PATH" "$PLUGIN_SLUG"
)

rm -rf "$STAGE_DIR"

echo "Build complete: $ZIP_PATH"
