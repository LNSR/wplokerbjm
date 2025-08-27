#!/usr/bin/env bash
set -euo pipefail

# Expected environment variables:
# SSH_PRIVATE_KEY, SSH_KEY_PASSPHRASE (optional), HOST, SSH_PORT

mkdir -p ~/.ssh
echo "Writing deploy key to a secure temporary file"
TMP_KEY=$(mktemp)
# Write the private key safely; expand any literal "\\n" sequences into real newlines
# and ensure a trailing newline (helps when secrets were stored with literal \n).
printf '%b\n' "$SSH_PRIVATE_KEY" > "$TMP_KEY"
chmod 600 "$TMP_KEY"

# If a passphrase is provided, remove it so ssh-add can use the key non-interactively
if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
  echo "Removing passphrase from key"
  # Attempt to remove passphrase; if ssh-keygen fails the key file may be malformed.
  ssh-keygen -p -P "$SSH_KEY_PASSPHRASE" -N "" -f "$TMP_KEY" || true
fi

# Minimal debug helpers (do not print the private key): show file mode and size
echo "DEBUG: key file mode: $(stat -c %a "$TMP_KEY" 2>/dev/null || true)" >&2
echo "DEBUG: key file size: $(stat -c %s "$TMP_KEY" 2>/dev/null || true) bytes" >&2

eval "$(ssh-agent -s)"
ssh-add "$TMP_KEY"

# Ensure known_hosts contains the host fingerprint (use provided port or default 22)
PORT=${SSH_PORT:-22}
ssh-keyscan -p $PORT $HOST >> ~/.ssh/known_hosts || true

# Persist the chosen key path for use in subsequent steps (safer than hardcoding /tmp)
echo "DEPLOY_KEY_PATH=$TMP_KEY" >> "$GITHUB_ENV"

echo "Prepare SSH key script finished"
