#!/usr/bin/env bash
set -euo pipefail

# Expected environment variables: DEPLOY_KEY_PATH, SSH_KEY_PASSPHRASE (optional)
KEY_PATH=${DEPLOY_KEY_PATH:-}
if [ -z "$KEY_PATH" ]; then
  echo "ERROR: DEPLOY_KEY_PATH is not set; prepare step may have failed"
  exit 1
fi

# Quick check: ssh-keygen -y fails if the key is passphrase-protected or invalid
if ssh-keygen -y -f "$KEY_PATH" >/dev/null 2>&1; then
  echo "Deploy key is not passphrase-protected or is already unlocked"
  exit 0
fi

if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
  echo "Deploy key appears encrypted; attempting to strip passphrase using SSH_KEY_PASSPHRASE"
  ssh-keygen -p -P "$SSH_KEY_PASSPHRASE" -N "" -f "$KEY_PATH" || true
  if ssh-keygen -y -f "$KEY_PATH" >/dev/null 2>&1; then
    echo "Passphrase removed from deploy key"
    exit 0
  fi
fi

echo "ERROR: $KEY_PATH is passphrase-protected or invalid and could not be unlocked non-interactively."
echo "Options: (1) set the SSH_KEY_PASSPHRASE secret to the key's passphrase so the workflow can strip it;"
echo "         (2) use an unencrypted private key in SSH_PRIVATE_KEY;"
exit 1
