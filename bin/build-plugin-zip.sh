#!/usr/bin/env bash
# Build a WordPress-installable zip whose root folder is always "remember/"
# (GitHub's auto "Source code" zip uses remember-<tag>/ and breaks upgrades.)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -E '^\s*\*\s*Version:' "$ROOT/remember.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
	echo "Could not read Version from remember.php" >&2
	exit 1
fi

OUT_DIR="$ROOT/dist"
STAGE="$OUT_DIR/stage"
ZIP_NAME="remember.zip"
ZIP_VERSIONED="remember-${VERSION}.zip"

rm -rf "$STAGE"
mkdir -p "$STAGE/remember" "$OUT_DIR"

# Copy plugin payload into stage/remember/ (never a versioned folder name).
rsync -a \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='.vscode/' \
	--exclude='.cursor/' \
	--exclude='dist/' \
	--exclude='bin/' \
	--exclude='.DS_Store' \
	--exclude='.gitignore' \
	--exclude='*.zip' \
	"$ROOT/" "$STAGE/remember/"

(
	cd "$STAGE"
	rm -f "$OUT_DIR/$ZIP_NAME" "$OUT_DIR/$ZIP_VERSIONED"
	zip -rq "$OUT_DIR/$ZIP_NAME" remember
	cp "$OUT_DIR/$ZIP_NAME" "$OUT_DIR/$ZIP_VERSIONED"
)

# Sanity: archive must contain remember/remember.php, not remember-1.x.y/
listing="$(unzip -l "$OUT_DIR/$ZIP_NAME")"
if ! grep -q 'remember/remember.php' <<<"$listing"; then
	echo "Zip layout check failed: remember/remember.php missing" >&2
	exit 1
fi
if grep -qE '(^|[[:space:]])remember-[0-9]+\.[0-9]+' <<<"$listing"; then
	echo "Zip layout check failed: versioned top-level folder present" >&2
	exit 1
fi

echo "Built $OUT_DIR/$ZIP_NAME and $OUT_DIR/$ZIP_VERSIONED (root folder: remember/)"
echo "$listing" | head -20
