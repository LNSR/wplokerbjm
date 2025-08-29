#!/usr/bin/env bash
set -euo pipefail

# Expected environment variables:
# SSH_PRIVATE_KEY, SSH_USER, HOST, SSH_PORT, REMOTE_PATH, SRC_DIR, DRY_RUN,
# RCLONE_TRANSFERS, RCLONE_CHECKERS, RCLONE_RETRIES, RCLONE_LOW_RETRIES, RCLONE_LOG_LEVEL

echo "Starting deploy (no persistent logs will be written)"

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

rclone config create sftpdeploy sftp host ${HOST} user ${SSH_USER} port ${SSH_PORT:-22} use-agent true

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

RCLONE_BASE_OPTS="--progress --transfers ${RCLONE_TRANSFERS} --checkers ${RCLONE_CHECKERS} --sftp-disable-hashcheck --retries ${RCLONE_RETRIES} --low-level-retries ${RCLONE_LOW_RETRIES} --timeout 1m --contimeout 1m --log-level ${RCLONE_LOG_LEVEL}"

if [ "${DRY_RUN:-false}" = "true" ]; then
  DRY_FLAG="--dry-run"
else
  DRY_FLAG=""
fi

# Test rclone remote connectivity before heavy operations
if ! rclone lsd sftpdeploy: >/dev/null 2>&1; then
  echo "ERROR: cannot list remote 'sftpdeploy:' — connection may be misconfigured"
  echo "Try checking ssh-auth (run details) and secrets"
  exit 1
fi

for p in "${SELECTED[@]}"; do
  if [ ! -e "$p" ]; then
    echo "Skipping $p; not present"
    continue
  fi

  if [ -f "$p" ]; then
    REMOTE_DIR="${REMOTE_PATH%/}"
    echo "rclone copy $p -> sftpdeploy:${REMOTE_DIR}/ $DRY_FLAG"
    rclone copy $DRY_FLAG $RCLONE_BASE_OPTS "$p" "sftpdeploy:${REMOTE_DIR}/" 2>&1 || true
    echo "FINISHED: file $p"
  else
    REMOTE_TARGET="${REMOTE_PATH%/}/$p"
    echo "rclone sync $p -> sftpdeploy:$REMOTE_TARGET $DRY_FLAG"
    rclone sync $DRY_FLAG $RCLONE_BASE_OPTS "$p" "sftpdeploy:$REMOTE_TARGET" 2>&1 || true
    echo "FINISHED: dir $p"
  fi
done
echo "---- DEPLOY SUMMARY ----"
echo "Date: $(date -u)"
echo "Rclone options: $RCLONE_BASE_OPTS"
echo "Note: logs are not persisted; check the run logs in Actions for details."
# cleanup: stop agent and remove temporary private key file
ssh-agent -k || true
if [ -n "${KEY_PATH:-}" ]; then
  rm -f "$KEY_PATH" || true
fi
