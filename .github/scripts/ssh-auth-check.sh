#!/usr/bin/env bash
set +e
PORT=${SSH_PORT:-22}
KEY_PATH=${DEPLOY_KEY_PATH:-/tmp/deploy_key}
echo "Testing SSH auth to ${SSH_USER}@${HOST} port $PORT"
# Use IdentitiesOnly to ensure ssh uses only the provided key; avoid verbose debug output
ssh -i "$KEY_PATH" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=10 -p $PORT ${SSH_USER}@${HOST} 'echo SSH_AUTH_OK' >/dev/null 2>&1 || true