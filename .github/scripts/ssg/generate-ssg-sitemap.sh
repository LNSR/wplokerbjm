#!/usr/bin/env bash
set -euo pipefail

# Wrapper script for SSG sitemap workflow: assumes SSG output exists in
# ${SRC_DIR}/assets/ssg (the workflow already runs the generator step).
# This script will sync that directory to the remote via rclone.

echo "SSG sitemap deploy wrapper starting"

# Check system resources before starting (lightweight check for sync operations)
echo "Checking system resources..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}' 2>/dev/null || echo "N/A")
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}' 2>/dev/null || echo "N/A")

if [ "$MEMORY_USAGE" != "N/A" ] && [ "$CPU_USAGE" != "N/A" ]; then
    echo "Memory usage: ${MEMORY_USAGE}%"
    echo "CPU usage: ${CPU_USAGE}%"
    
    # Only warn if extremely high usage (more lenient than generation script)
    if [ "$MEMORY_USAGE" -gt 90 ] || [ "$CPU_USAGE" -gt 90 ]; then
        echo "⚠️  Warning: System is heavily loaded (${MEMORY_USAGE}% memory, ${CPU_USAGE}% CPU)"
        echo "SSG sync may be slower than usual"
    fi
else
    echo "Resource monitoring not available (non-Linux system)"
fi

# Expected environment variables (inherited from workflow):
# SSH_PRIVATE_KEY, SSH_USER, HOST, SSH_PORT, REMOTE_PATH, DRY_RUN,
# RCLONE_TRANSFERS, RCLONE_CHECKERS, RCLONE_RETRIES, RCLONE_LOW_RETRIES, RCLONE_LOG_LEVEL

if [ "${DRY_RUN:-false}" = "true" ]; then
  DRY_FLAG="--dry-run"
else
  DRY_FLAG=""
fi

# Use relative path since we're already in the theme directory
SSG_SRC_DIR="./assets/ssg"
if [ ! -d "$SSG_SRC_DIR" ]; then
  echo "ERROR: SSG directory not found at $SSG_SRC_DIR"
  echo "Current working directory: $(pwd)"
  echo "Looking for: $SSG_SRC_DIR"
  exit 1
fi

echo "SSG source directory: $SSG_SRC_DIR"

REMOTE_SSG_DIR="${REMOTE_PATH}/assets/ssg"

echo "Preparing SSH agent and deploy key"
eval "$(ssh-agent -s)"
KEY_PATH=${DEPLOY_KEY_PATH:-/tmp/deploy_key}
chmod 600 "$KEY_PATH" || true
if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
  ssh-keygen -p -P "$SSH_KEY_PASSPHRASE" -N "" -f "$KEY_PATH" || true
fi
ssh-add "$KEY_PATH"
if ! ssh-add -l >/dev/null 2>&1; then
  echo 'ssh-agent has no keys; failing' && exit 1
fi
PORT=${SSH_PORT:-22}
mkdir -p ~/.ssh
ssh-keyscan -p "$PORT" "$HOST" >> ~/.ssh/known_hosts || true

echo "Configuring rclone remote 'sftpdeploy'"
rclone config create sftpdeploy sftp host "${HOST}" user "${SSH_USER}" port "${SSH_PORT:-22}" use-agent true disable_hashcheck true || {
    echo "❌ Failed to create rclone config"
    exit 1
}

cd "$SSG_SRC_DIR"

RCLONE_BASE_OPTS=(
  --progress
  --transfers "${RCLONE_TRANSFERS}"
  --checkers "${RCLONE_CHECKERS}"
  --sftp-disable-hashcheck
  --retries "${RCLONE_RETRIES}"
  --low-level-retries "${RCLONE_LOW_RETRIES}"
  --timeout 5m
  --contimeout 30s
  --log-level "${RCLONE_LOG_LEVEL}"
  --bwlimit "${RCLONE_BWLIMIT:-5M}"
  --tpslimit "${RCLONE_TPSLIMIT:-2}"
  --tpslimit-burst 4
  --delete-after
  --fast-list
)

echo "Ensuring remote SSG directory exists: $REMOTE_SSG_DIR"
rclone mkdir $DRY_FLAG "sftpdeploy:$REMOTE_SSG_DIR" 2>&1 || true

echo "Running rclone sync"
echo -n "rclone sync ${DRY_FLAG} "; printf '%s ' "${RCLONE_BASE_OPTS[@]}"; echo ". -> sftpdeploy:${REMOTE_SSG_DIR}"

# Execute rclone using array expansion for base opts
rclone sync ${DRY_FLAG} "${RCLONE_BASE_OPTS[@]}" "." "sftpdeploy:${REMOTE_SSG_DIR}" 2>&1 || true

echo "FINISHED: SSG sitemap sync complete"
echo "Source: $PWD"
echo "Destination: sftpdeploy:$REMOTE_SSG_DIR"

# cleanup
ssh-agent -k || true
if [ -n "${KEY_PATH:-}" ]; then
  rm -f "$KEY_PATH" || true
fi

exit 0
