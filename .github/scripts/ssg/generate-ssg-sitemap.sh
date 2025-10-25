#!/usr/bin/env bash
set -euo pipefail

# Wrapper script for SSG sitemap workflow: assumes SSG output exists in
# ${SRC_DIR}/assets/ssg (the workflow already runs the generator step).
# This script will tar the directory, transfer via SCP, and decompress on remote.

echo "SSG sitemap deploy wrapper starting"

# Check system resources before starting (lightweight check for deploy operations)
echo "Checking system resources..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}' 2>/dev/null || echo "N/A")
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{printf "%.0f", 100 - $1}' 2>/dev/null || echo "N/A")

if [ "$MEMORY_USAGE" != "N/A" ] && [ "$CPU_USAGE" != "N/A" ]; then
    echo "Memory usage: ${MEMORY_USAGE}%"
    echo "CPU usage: ${CPU_USAGE}%"
    
    # Only warn if extremely high usage (more lenient than generation script)
    if [ "$MEMORY_USAGE" -gt 90 ] || [ "$CPU_USAGE" -gt 90 ]; then
        echo "⚠️  Warning: System is heavily loaded (${MEMORY_USAGE}% memory, ${CPU_USAGE}% CPU)"
        echo "SSG deploy may be slower than usual"
    fi
else
    echo "Resource monitoring not available (non-Linux system)"
fi

# Expected environment variables (inherited from workflow):
# SSH_PRIVATE_KEY, SSH_USER, HOST, SSH_PORT, REMOTE_PATH, DRY_RUN

if [ "${DRY_RUN:-false}" = "true" ]; then
  echo "DRY RUN mode enabled"
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

TAR_FILE="/tmp/ssg.tar.gz"
REMOTE_TAR="${REMOTE_PATH}/assets/ssg.tar.gz"

if [ "${DRY_RUN:-false}" = "true" ]; then
  echo "DRY RUN: Would compress $SSG_SRC_DIR to $TAR_FILE"
else
  echo "Compressing SSG directory to $TAR_FILE"
  tar -czf "$TAR_FILE" "$SSG_SRC_DIR" || {
    echo "❌ Failed to create tar archive"
    exit 1
  }
fi

echo "Transferring tar archive via SCP"
if [ "${DRY_RUN:-false}" = "true" ]; then
  echo "DRY RUN: Would SCP $TAR_FILE to ${SSH_USER}@${HOST}:${REMOTE_TAR}"
else
  scp -P "$PORT" -l 5000 "$TAR_FILE" "${SSH_USER}@${HOST}:${REMOTE_TAR}" || {
    echo "❌ Failed to transfer tar archive"
    exit 1
  }
fi

echo "Decompressing on remote server"
if [ "${DRY_RUN:-false}" = "true" ]; then
  echo "DRY RUN: Would run: ssh -p $PORT $SSH_USER@$HOST 'cd ${REMOTE_PATH} && if [ -d assets/ssg ]; then rm -rf assets/ssg; fi && tar -xzf assets/ssg.tar.gz && rm assets/ssg.tar.gz'"
else
  ssh -p "$PORT" "$SSH_USER@$HOST" "cd '${REMOTE_PATH}' && if [ -d assets/ssg ]; then rm -rf assets/ssg; fi && tar -xzf assets/ssg.tar.gz && rm assets/ssg.tar.gz" || {
    echo "❌ Failed to decompress on remote"
    exit 1
  }
fi

# Cleanup local tar file
if [ "${DRY_RUN:-false}" != "true" ] && [ -f "$TAR_FILE" ]; then
  rm "$TAR_FILE"
fi

echo "FINISHED: SSG sitemap deploy complete"
echo "Source: $SSG_SRC_DIR (tarred and transferred)"
echo "Destination: ${REMOTE_PATH}/assets/ssg (decompressed)"

# cleanup
ssh-agent -k || true
if [ -n "${KEY_PATH:-}" ]; then
  rm -f "$KEY_PATH" || true
fi

exit 0
