#!/usr/bin/env bash
set -euo pipefail

# Expected environment variables:
# SSH_PRIVATE_KEY, SSH_USER, HOST, SSH_PORT, REMOTE_PATH, MU_PLUGIN_REMOTE_PATH, SRC_DIR, DRY_RUN,
# RCLONE_TRANSFERS, RCLONE_CHECKERS, RCLONE_RETRIES, RCLONE_LOW_RETRIES, RCLONE_LOG_LEVEL

echo "Starting deploy (no persistent logs will be written)"
echo "Performance settings: Transfers=${RCLONE_TRANSFERS}, Checkers=${RCLONE_CHECKERS}, BW Limit=5M, TPS Limit=2"
START_TIME=$(date +%s)

# Check system resources before starting deployment
echo "Checking system resources..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}' 2>/dev/null || echo "N/A")
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}' 2>/dev/null || echo "N/A")

if [ "$MEMORY_USAGE" != "N/A" ] && [ "$CPU_USAGE" != "N/A" ]; then
    echo "Memory usage: ${MEMORY_USAGE}%"
    echo "CPU usage: ${CPU_USAGE}%"
    
    # Warn if system is already heavily loaded
    if [ "$MEMORY_USAGE" -gt 85 ] || [ "$CPU_USAGE" -gt 85 ]; then
        echo "⚠️  Warning: System is heavily loaded (${MEMORY_USAGE}% memory, ${CPU_USAGE}% CPU)"
        echo "Deployment may be slower than usual"
    fi
else
    echo "Resource monitoring not available (non-Linux system)"
fi

eval "$(ssh-agent -s)"
KEY_PATH=${DEPLOY_KEY_PATH:-/tmp/deploy_key}
chmod 600 "$KEY_PATH" || true
if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
  ssh-keygen -p -P "$SSH_KEY_PASSPHRASE" -N "" -f "$KEY_PATH" || true
fi
ssh-add "$KEY_PATH"
# Suppress direct fingerprint output; just ensure an identity exists
if ! ssh-add -l >/dev/null 2>&1; then
  echo 'ssh-agent has no keys; failing' && exit 1
fi
PORT=${SSH_PORT:-22}
mkdir -p ~/.ssh
ssh-keyscan -p $PORT $HOST >> ~/.ssh/known_hosts || true

rclone config create sftpdeploy sftp host ${HOST} user ${SSH_USER} port ${SSH_PORT:-22} use-agent true disable-hashcheck true

cd "$SRC_DIR"

SELECTED=(
  "cache"
  "inc"
  "footer.php"
  "header.php"
  "functions.php"
  "composer.json"
  "page-homepage.php"
  "page-pasang-lowongan.php"
  "single-lowongan.php"
  "archive-lowongan.php"
  "assets"
  "vendor"
)

PIDS=()

RCLONE_BASE_OPTS="--progress --transfers ${RCLONE_TRANSFERS} --checkers ${RCLONE_CHECKERS} --retries ${RCLONE_RETRIES} --low-level-retries ${RCLONE_LOW_RETRIES} --timeout 5m --contimeout 60s --log-level ${RCLONE_LOG_LEVEL} --bwlimit 5M --tpslimit 2 --tpslimit-burst 4 --delete-after"

if [ "${DRY_RUN:-false}" = "true" ]; then
  DRY_FLAG="--dry-run"
else
  DRY_FLAG=""
fi

# Test rclone remote connectivity before heavy operations
echo "Testing server connectivity..."
if ! rclone lsd sftpdeploy: >/dev/null 2>&1; then
  echo "ERROR: cannot list remote 'sftpdeploy:' — connection may be misconfigured"
  echo "Try checking ssh-auth (run details) and secrets"
  exit 1
fi
echo "✅ Server connection established"

for p in "${SELECTED[@]}"; do
  if [ ! -e "$p" ]; then
    echo "Skipping $p; not present"
    continue
  fi

  if [ -f "$p" ]; then
    REMOTE_DIR="${REMOTE_PATH%/}"
    echo "Starting rclone copy $p -> sftpdeploy:${REMOTE_DIR}/ $DRY_FLAG"
    rclone copy $DRY_FLAG $RCLONE_BASE_OPTS "$p" "sftpdeploy:${REMOTE_DIR}/" 2>&1 || true &
    PIDS+=($!)
    sleep 3
  else
    REMOTE_TARGET="${REMOTE_PATH%/}/$p"
    echo "Starting rclone sync $p -> sftpdeploy:$REMOTE_TARGET $DRY_FLAG"
    
    # Exclude ssg/ directory when syncing assets
    if [ "$p" = "assets" ]; then
      rclone sync $DRY_FLAG $RCLONE_BASE_OPTS --exclude "ssg/**" "$p" "sftpdeploy:$REMOTE_TARGET" 2>&1 || true &
      PIDS+=($!)
      sleep 3
    else
      rclone sync $DRY_FLAG $RCLONE_BASE_OPTS "$p" "sftpdeploy:$REMOTE_TARGET" 2>&1 || true &
      PIDS+=($!)
      sleep 3
    fi
  fi
done

# Start mu-plugin sync if needed
if [ -n "${MU_PLUGIN_REMOTE_PATH:-}" ]; then
  MU_PLUGIN_FILE="../../mu-plugins/astra-child-bootstrap.php"
  if [ -f "$MU_PLUGIN_FILE" ]; then
    echo "Starting syncing astra-child-bootstrap.php to $MU_PLUGIN_REMOTE_PATH"
    rclone copy $DRY_FLAG $RCLONE_BASE_OPTS "$MU_PLUGIN_FILE" "sftpdeploy:$MU_PLUGIN_REMOTE_PATH" 2>&1 || true &
    PIDS+=($!)
    sleep 0.5
  else
    echo "astra-child-bootstrap.php not found at $MU_PLUGIN_FILE"
  fi
else
  echo "MU_PLUGIN_REMOTE_PATH not set, skipping mu-plugins sync"
fi

# Wait for all rclone operations to complete
for pid in "${PIDS[@]}"; do
  wait $pid
done
echo "All rclone operations completed"
echo "---- DEPLOY SUMMARY ----"
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
echo "Date: $(date -u)"
echo "Duration: ${DURATION} seconds"
echo "Rclone options: $RCLONE_BASE_OPTS"
echo "Note: Using balanced settings to prevent server overload (BW: 5M, TPS: 2, concurrent operations, delete-after for safety)"
echo "Check the run logs in Actions for details."



# cleanup: stop agent and remove temporary private key file
ssh-agent -k || true
if [ -n "${KEY_PATH:-}" ]; then
  rm -f "$KEY_PATH" || true
fi
