#!/usr/bin/env bash
set -euo pipefail

# SSG Selective Generation and Deployment Script
# Generates static pages for specific paths and deploys them via rclone

echo "Starting SSG generation and deployment..."

# Check system resources before starting
echo "Checking system resources..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%d", $3/$2 * 100}')
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{printf "%d", 100 - $1}')

echo "Memory usage: ${MEMORY_USAGE}%"
echo "CPU usage: ${CPU_USAGE}%"

# Warn if system is already heavily loaded
if [ "$MEMORY_USAGE" -gt 80 ] || [ "$CPU_USAGE" -gt 80 ]; then
  echo "⚠️  Warning: System is heavily loaded (${MEMORY_USAGE}% memory, ${CPU_USAGE}% CPU)"
  echo "Consider reducing parallel processes or SSG_MAX_PARALLEL"
fi

# Expected environment variables:
# GITHUB_PATHS, GITHUB_REASON, SSH_PRIVATE_KEY, SSH_USER, HOST, SSH_PORT,
# REMOTE_PATH, SRC_DIR, DRY_RUN, RCLONE_TRANSFERS, RCLONE_CHECKERS,
# RCLONE_RETRIES, RCLONE_LOW_RETRIES, RCLONE_LOG_LEVEL

# Set DRY_FLAG early to avoid unbound variable error
if [ "${DRY_RUN:-false}" = "true" ]; then
  DRY_FLAG="--dry-run"
else
  DRY_FLAG=""
fi

# Check if paths are provided
if [[ -n "${GITHUB_PATHS:-}" ]]; then
    echo "Regenerating specific paths..."
    echo "Trigger reason: ${GITHUB_REASON:-unknown}"

    # Count paths instead of listing them
    PATH_COUNT=$(echo "$GITHUB_PATHS" | jq '. | length')
    echo "Found $PATH_COUNT paths to regenerate"

    # Parse JSON paths array and generate each URL (without logging URLs)
    PATHS=$(echo "$GITHUB_PATHS" | jq -r '.[]')
    GENERATED_COUNT=0
    FAILED_PATHS=()
    
    # Check if we should run in parallel (more than 5 paths and not in dry run)
    if [ "$PATH_COUNT" -gt 5 ] && [ "${DRY_RUN:-false}" != "true" ]; then
        echo "🔄 Running in parallel mode for $PATH_COUNT paths"
        MAX_PARALLEL=${SSG_MAX_PARALLEL:-2}  # Limit parallel processes to prevent resource exhaustion
        echo "Using max $MAX_PARALLEL parallel processes"
        
        # Create a timestamp file to track what we generate
        touch /tmp/ssg_start_time
        
        # Generate pages in parallel using GNU parallel
        printf '%s\n' "${PATHS[@]}" | parallel --no-notice -j "$MAX_PARALLEL" '
            path={}
            # Extract path from URL and convert to file structure
            url_path=$(echo "$path" | sed "s|https://[^/]*||")
            
            # Remove trailing slash except for root
            if [ "$url_path" != "/" ] && [[ "$url_path" == */ ]]; then
                url_path="${url_path%/}"
            fi
            
            # Handle root path
            if [ "$url_path" = "/" ] || [ -z "$url_path" ]; then
                filename="index.html"
            else
                # Add .html extension if not present
                if [[ "$url_path" == *.html ]]; then
                    filename="$url_path"
                else
                    filename="$url_path.html"
                fi
            fi
            
            # Remove leading slash for file path
            filename="${filename#/}"
            output_file="./assets/ssg/${filename}"
            
            echo "Generating: $url_path -> $output_file"
            echo "Running: bun run ssg \"$path\" \"$output_file\""

            # Add timeout and memory limits to prevent resource exhaustion
            if timeout 180 bun run ssg "$path" "$output_file"; then
                echo "✅ Completed: $path"
            else
                echo "❌ Timeout or error occurred for $path"
                exit 1
            fi
        '
        
        # Count generated files (approximate)
        GENERATED_COUNT=$(find ./assets/ssg -type f -name "*.html" -newer /tmp/ssg_start_time 2>/dev/null | wc -l || echo "$PATH_COUNT")
        
    else
        echo "🔄 Running in sequential mode"
        for path in $PATHS; do
            # Extract path from URL and convert to file structure
            url_path=$(echo "$path" | sed 's|https://[^/]*||')

            # Remove trailing slash except for root
            if [ "$url_path" != "/" ] && [[ "$url_path" == */ ]]; then
                url_path="${url_path%/}"
            fi

            # Handle root path
            if [ "$url_path" = "/" ] || [ -z "$url_path" ]; then
                filename="index.html"
            else
                # Add .html extension if not present
                if [[ "$url_path" == *.html ]]; then
                    filename="$url_path"
                else
                    filename="$url_path.html"
                fi
            fi

            # Remove leading slash for file path
            filename="${filename#/}"
            output_file="./assets/ssg/${filename}"

            echo "Generating: $url_path -> $output_file"

      echo "Running: bun run ssg \"$path\" \"$output_file\""

      # Add timeout and memory limits to prevent resource exhaustion
      timeout 180 bun run ssg "$path" "$output_file" || {
        echo "❌ Timeout or error occurred for $path"
        FAILED_PATHS+=("$path")
        continue
      }

            GENERATED_COUNT=$((GENERATED_COUNT + 1))
            echo "✅ Completed: $path"
        done
    fi
    
    echo "Generated $GENERATED_COUNT pages"
    
    # Report failed paths if any (only for sequential mode)
    if [ ${#FAILED_PATHS[@]} -gt 0 ]; then
        echo "⚠️  Failed to generate ${#FAILED_PATHS[@]} pages:"
        for failed_path in "${FAILED_PATHS[@]}"; do
            echo "  - $failed_path"
        done
    fi

    # Force cleanup of any remaining processes
    echo "Cleaning up processes..."
    # More targeted cleanup - only kill our specific processes
    pkill -f "bun.*ssg" || true
    pkill -f "playwright.*chromium.*--remote-debugging-port" || true
    # Give processes time to cleanup gracefully
    sleep 3
    # Force kill any remaining hung processes
    pkill -9 -f "bun.*ssg" || true
    pkill -9 -f "playwright" || true
    pkill -9 -f "chromium.*--headless" || true
    
    # Additional cleanup for any parallel processes
    pkill -f "parallel" || true
    sleep 1

else
    echo "No specific paths provided - skipping selective generation"
    echo "Use the 'paths' input to specify which pages to regenerate"
fi

# Verify output
echo "Checking SSG output..."
if [ -d "assets/ssg" ]; then
    echo "SSG directory exists"
    echo "Total SSG files: $(find assets/ssg -type f -name "*.html" | wc -l)"
else
    echo "ERROR: SSG directory not found!"
    exit 1
fi

echo "SSG generation completed successfully!"

# Brief pause before deployment to prevent server overload
echo "Pausing 5 seconds before deployment..."
sleep 5

echo "Starting SSG deploy (no persistent logs will be written)"

# Set DRY_FLAG early to avoid unbound variable error
if [ "${DRY_RUN:-false}" = "true" ]; then
  DRY_FLAG="--dry-run"
else
  DRY_FLAG=""
fi

# Setup SSH agent and keys FIRST
eval "$(ssh-agent -s)"
KEY_PATH=${DEPLOY_KEY_PATH:-/tmp/ssg_deploy_key_$$}
echo "$SSH_PRIVATE_KEY" > "$KEY_PATH"
chmod 600 "$KEY_PATH"
if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
  if ! ssh-keygen -p -P "$SSH_KEY_PASSPHRASE" -N "" -f "$KEY_PATH"; then
    echo "Failed to remove passphrase from SSH key"
    exit 1
  fi
fi
if ! ssh-add "$KEY_PATH"; then
  echo "Failed to add SSH key to agent"
  exit 1
fi
# Suppress direct fingerprint output; just ensure an identity exists
if ! ssh-add -l >/dev/null 2>&1; then
  echo '❌ ssh-agent has no keys; failing' && exit 1
fi
PORT=${SSH_PORT:-22}
mkdir -p ~/.ssh
ssh-keyscan -p $PORT $HOST >> ~/.ssh/known_hosts || true

# Create rclone config AFTER SSH setup
rclone config create sftpdeploy sftp \
  host ${HOST} \
  user ${SSH_USER} \
  port ${SSH_PORT:-22} \
  use-agent true \
  disable_hashcheck true \
  || { echo "❌ Failed to create rclone config"; exit 1; }

# Change to SSG assets directory
SSG_SRC_DIR="./assets/ssg"
if [ ! -d "$SSG_SRC_DIR" ]; then
  echo "ERROR: SSG directory not found at $SSG_SRC_DIR"
  exit 1
fi

cd "$SSG_SRC_DIR"

echo "Copying SSG assets from: $SSG_SRC_DIR"
echo "Remote destination: ${REMOTE_PATH}/assets/ssg"

RCLONE_BASE_OPTS="--progress --transfers ${RCLONE_TRANSFERS} --checkers ${RCLONE_CHECKERS} --sftp-disable-hashcheck --retries ${RCLONE_RETRIES} --low-level-retries ${RCLONE_LOW_RETRIES} --timeout 5m --contimeout 30s --log-level ${RCLONE_LOG_LEVEL} --bwlimit ${RCLONE_BWLIMIT:-5M} --tpslimit ${RCLONE_TPSLIMIT:-2} --tpslimit-burst 4 --fast-list"

# Test rclone remote connectivity before heavy operations
if ! rclone lsd sftpdeploy: >/dev/null 2>&1; then
  echo "ERROR: cannot list remote 'sftpdeploy:' — connection may be misconfigured"
  echo "Try checking ssh-auth (run details) and secrets"
  exit 1
fi

# Create remote SSG directory if it doesn't exist
REMOTE_SSG_DIR="${REMOTE_PATH}/assets/ssg"

echo "Ensuring remote SSG directory exists: $REMOTE_SSG_DIR"
rclone mkdir $DRY_FLAG "sftpdeploy:$REMOTE_SSG_DIR" 2>&1 || true

# Copy all SSG content to remote assets/ssg directory
echo "rclone copy . -> sftpdeploy:$REMOTE_SSG_DIR $DRY_FLAG"
if rclone copy $DRY_FLAG $RCLONE_BASE_OPTS "." "sftpdeploy:$REMOTE_SSG_DIR" 2>&1; then
    echo "✅ SSG copy completed successfully"
else
    echo "❌ SSG copy failed"
    exit 1
fi

echo "FINISHED: SSG copy complete"
echo "---- SSG DEPLOY SUMMARY ----"
echo "Date: $(date -u)"
echo "Source: $PWD"
echo "Destination: sftpdeploy:$REMOTE_SSG_DIR"
echo "Rclone options: $RCLONE_BASE_OPTS"
echo "SSG files copied: $(find . -type f -name "*.html" | wc -l) HTML files"
echo "Note: logs are not persisted; check the run logs in Actions for details."

# cleanup: stop agent and remove temporary private key file
ssh-agent -k || true
if [ -n "${KEY_PATH:-}" ]; then
  rm -f "$KEY_PATH" || true
fi

echo "SSG generation and deployment completed successfully!"