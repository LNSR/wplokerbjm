#!/usr/bin/env zsh
set -a
source deployment_local.env 2>/dev/null || true
set -euo pipefail
set +a

# Parse --dry-run flag
DRY_RUN=""
for arg in "$@"; do
  if [[ "$arg" == "--dry-run" ]]; then
    DRY_RUN="--dry-run"
    echo "🔍 DRY RUN MODE — no files will be transferred, no remote commands executed"
  fi
done

#! Manual deploy script using rsync from local machine
# * Make sure remote target is mounted and ssh-agent configured
# Expected environment variables:
# SRC_DIR: Local source directory (theme root)
# REMOTE_PATH: Remote target directory
# SSH_PATH: SSH Path where wordpress web root located to execute wpcli 
# SSH_HOST: Name SSH in your ssh-agent

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
# Ensure required variables are set
: "${SRC_DIR:? "SRC_DIR environment variable is missing"}"
: "${REMOTE_PATH:? "REMOTE_PATH environment variable is missing"}"
: "${SSH_PATH:? "SSH_PATH environment variable is missing"}"
: "${SSH_HOST:? "SSH_HOST environment variable is missing"}"

echo "Starting manual deploy with rsync"
START_TIME=$(date +%s)

cd "$SRC_DIR"

# echo "Running tests before deployment..."
# if ! composer test; then
# echo "❌ Tests failed, aborting deployment"
# exit 1
# fi
# echo "✅ Tests passed, proceeding with deployment"

SELECTED=(
"cache"
"server"
"composer.json"
"index.php"
)

for p in "${SELECTED[@]}"; do
  if [ ! -e "$p" ]; then
    echo "Skipping $p; not present"
    continue
  fi

  echo "Syncing $p to $REMOTE_PATH"
  rsync -avz $DRY_RUN --delete-after --bwlimit=500 "$p" "$REMOTE_PATH/"
  # Add a short delay to prevent overwhelming the shared hosting
  sleep 5
done

echo "All rsync operations completed"

# Brief pause before running remote commands to allow server to settle
sleep 5

REMOTE_EXEC="composer install --no-dev --prefer-dist --no-interaction --classmap-authoritative --apcu-autoloader --no-scripts && wp litespeed-purge all && composer dump-autoload --apcu -a -o"

echo "Purging LiteSpeed cache and running composer dump-autoload on remote server"
if [[ -n "$DRY_RUN" ]]; then
  echo "[DRY RUN] Would execute: ssh $SSH_HOST \"cd $SSH_PATH && $REMOTE_EXEC\""
else
  ssh $SSH_HOST "cd \"$SSH_PATH\" && $REMOTE_EXEC"
fi
echo "---- DEPLOY SUMMARY ----"
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
echo "Date: $(date -u)"
echo "Duration: ${DURATION} seconds"