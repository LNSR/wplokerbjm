#!/usr/bin/env zsh
: ${REDIS_PWD:? "Redis Password env missing"}
: ${WP_ENV:? "WP_ENV env missing"}

watch_paths=(server composer.json composer.lock)

# Exit if current directory isn't wplokerbjm
echo "Running in directory $(pwd)"
if ! [[ $(pwd) == */wplokerbjm ]]; then
  echo "Error: Autoloadwatcher is not running in the correct directory." >&2
  exit 1
fi

if ! command -v inotifywait >/dev/null 2>&1; then
  echo "Error: inotifywait is not installed. Please install inotify-tools." >&2
  exit 1
fi

# Verify watched paths exist (allow files too)
for p in "${watch_paths[@]}"; do
  if [ -d "$p" ] || [ -f "$p" ]; then
    continue
  fi
  echo "Error: Watch path '$p' does not exist." >&2
  exit 1
done

pending_pid=""

# Process substitution keeps the while loop in the main subshell, preserving $pending_pid across iterations
while read -r file; do
  if [[ -n "$file" && "$file" =~ \.(php|json)$ ]]; then
    echo "[$(date +'%H:%M:%S')] Change detected in $file, setting 3s debounce timer..."

    # Cancel any previous pending hot reload task
    if [[ -n "$pending_pid" ]]; then
      kill -9 "$pending_pid" 2>/dev/null || true
    fi

    # Spawn debounced task chain
    (
      sleep 3

      echo "[$(date +'%H:%M:%S')] 🚀 Triggering WordPress Local Hot Reload Chain..." &
      docker restart "wordpress-${WP_ENV}" &

      echo "[$(date +'%H:%M:%S')] Flushing Redis..." &
      docker exec -i wordpress_redis redis-cli -a "${REDIS_PWD}" FLUSHALL > /dev/null 2>&1

      if [[ -d cache ]]; then
        echo "[$(date +'%H:%M:%S')] Clearing cache directory."
        rm -rf cache/*
      else
        echo "[$(date +'%H:%M:%S')] Cache directory not found."
      fi

      echo "[$(date +'%H:%M:%S')] 🚀 WordPress Local Hot Reload Chain Completed."
    ) &
    pending_pid=$!
  fi
done < <(inotifywait -m -r -e modify,create,delete --format '%w%f' "${watch_paths[@]}" \
  --exclude '(^|/)(node_modules|dist|assets/dist|vendor|\.git|\..*swp$|~$)')