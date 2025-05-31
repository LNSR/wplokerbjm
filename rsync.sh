#!/bin/bash

SRC="$HOME/Project/Lowker-site/astra-child/"
DEST="$HOME/Cloud Drive/jagoanhosting/staging.lowongankerjabanjarmasin.com/wp-content/themes/astra-child/"

DRYRUN=""
if [[ "$1" == "--dry-run" ]]; then
  DRYRUN="--dry-run"
fi

rsync -avz $DRYRUN --delete-after \
  --exclude="*.git" \
  --exclude="vendor" \
  --exclude="*.gitignore" \
  --exclude="node_modules" \
  --exclude="*.map" \
  --exclude="*.env" \
  --exclude="*.log" \
  --exclude="tests" \
  --exclude="*.md" \
  --exclude="*.txt" \
  --exclude="*.json" \
  --exclude="*.lock" \
  --exclude="cache" \
  --exclude="Notes" \
  --exclude=".vscode" \
  "$SRC" "$DEST"
