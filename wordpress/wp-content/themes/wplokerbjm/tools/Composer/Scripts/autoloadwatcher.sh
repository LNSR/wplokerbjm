#!/usr/bin/env zsh
setopt errexit nounset pipefail

: "${REDIS_PWD:?\"Redis Password env missing\"}"
: "${WP_ENV:?\"WP_ENV env missing\"}"

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
  if [[ ! -e "$p" ]]; then
    echo "Error: Watch path '$p' does not exist." >&2
    exit 1
  fi
done

debounce_time=3
lock_file="/tmp/wplokerbjm_reload.lock"

current_timer_pid=""

# Cleanup handler to ensure pending timers are killed on exit
cleanup_timer() {
  if [[ -n "$current_timer_pid" ]]; then
    kill -TERM "$current_timer_pid" 2>/dev/null || true
    wait "$current_timer_pid" 2>/dev/null || true
    current_timer_pid=""
  fi
}

trap cleanup_timer EXIT INT TERM

echo "[$(date +'%H:%M:%S')] 🔍 Watching for changes in: ${watch_paths[*]}"

while read -r file; do
  if [[ -n "$file" && "$file" =~ \.(php|json)$ ]]; then
    echo "[$(date +'%H:%M:%S')] Change detected in $file -> Resetting ${debounce_time}s debounce timer..."

    # Reset/Cancel any pending debounce timer
    cleanup_timer

    # 1. If an old debounce subshell is already waiting, kill it immediately
    if [[ -n "$current_timer_pid" ]] && kill -TERM "$current_timer_pid" 2>/dev/null; then
      kill "$current_timer_pid" 2>/dev/null
    fi

    # 2. Spawn the new debounce block in the background and track its PID
    (
      sleep "$debounce_time"

      exec {lock_fd}<>"$lock_file"
      if ! flock -n "$lock_fd"; then
        # If a reload is actively running, silent exit or handle clean queue
        exit 0
      fi

      trap 'rm -f "$lock_file"' EXIT

      echo "[$(date +'%H:%M:%S')] 🚀 Executing WordPress Local Hot Reload Chain..."

      if ! docker restart "wordpress-${WP_ENV}" >/dev/null 2>&1; then
        echo "❌ Warning: Docker restart encountered an issue."
      fi

      (
        docker exec -i wordpress_redis redis-cli -a "${REDIS_PWD}" FLUSHALL >/dev/null 2>&1 &
        if [[ -d cache ]]; then
          rm -rf cache &
        fi
        wait
      )

      echo "🚀 WordPress Local Hot Reload Chain Completed."
    ) &

    current_timer_pid=$!
  fi
done < <(inotifywait -m -r -e modify,create,delete --format '%w%f' "${watch_paths[@]}" \
  --exclude '(^|/)(node_modules|dist|assets/dist|vendor|\.git|\..*swp$|~$)')
