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

# Verify watched paths exist
for p in "${watch_paths[@]}"; do
  if [ -d "$p" ] || [ -f "$p" ]; then
    continue
  fi
  echo "Error: Watch path '$p' does not exist." >&2
  exit 1
done

pending_pid=""
debounce_time=5

while read -r file; do
  if [[ -n "$file" && "$file" =~ \.(php|json)$ ]]; then
    echo "[$(date +'%H:%M:%S')] Change in $file -> Setting ${debounce_time}s debounce timer..."

    if [[ -n "$pending_pid" ]]; then
      kill -15 "$pending_pid" 2>/dev/null || true
    fi

    (
      sleep "$debounce_time"
      echo "[$(date +'%H:%M:%S')] 🚀 Executing WordPress Local Hot Reload Chain..."

      # Run Docker restart in background
      docker restart "wordpress-${WP_ENV}" &

      # Run Redis flush and Cache clearing concurrently in the background
      (
        docker exec -i wordpress_redis redis-cli -a "${REDIS_PWD}" FLUSHALL > /dev/null 2>&1 &
        if [[ -d cache ]]; then
          rm -rf cache/* &
        fi
        wait
      ) &

      wait
      echo "[$(date +'%H:%M:%S')] 🚀 WordPress Local Hot Reload Chain Completed."
    ) &
    
    pending_pid=$!
  fi
done < <(inotifywait -m -r -e modify,create,delete --format '%w%f' "${watch_paths[@]}" \
  --exclude '(^|/)(node_modules|dist|assets/dist|vendor|\.git|\..*swp$|~$)')