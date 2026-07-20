#!/usr/bin/env zsh
: ${REDIS_PWD:? "Redis Password env missing"}
: ${WP_ENV:? "WP_ENV env missing"}
watch_paths=(server composer.json composer.lock)
# exit if pwd isnt wplokerbjm
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

# Continuous monitor with debouncing
# Watch only PHP and JSON changes and ignore common noisy dirs
inotifywait -m -r -e modify,create,delete --format '%w%f' "${watch_paths[@]}" \
  --exclude '(^|/)(node_modules|dist|assets/dist|vendor|\.git|\..*swp$|~$)' | while read -r file; do
  if [[ -n "$file" ]]; then
    if [[ "$file" =~ \.(php|json)$ ]]; then
      echo "[$(date +'%H:%M:%S')] Change detected in $file, scheduling autoload dump..."
      # Kill any previous pending composer run
      if [[ -n "$pending_pid" ]]; then
        kill "$pending_pid" 2>/dev/null || true
      fi
      # Run composer in background after a short delay (debounce)
      (
      sleep 2
      echo "[$(date +'%H:%M:%S')] 🚀 Triggering WordPress Local Hot Reload Chain..." &
      docker restart wordpress-${WP_ENV} &
      echo "[$(date +'%H:%M:%S')] Flushing Redis..." &
      docker exec -i wordpress_redis redis-cli -a ${REDIS_PWD} FLUSHALL > /dev/null 2>&1 &
      echo "[$(date +'%H:%M:%S')] Dumping Autoload..." &
      composer dump-autoload --apcu -a -o > /dev/null &
      if [[ -d cache ]]; then echo "[$(date +'%H:%M:%S')] Clearing cache directory." ; rm -rf cache/*; else echo "[$(date +'%H:%M:%S')] Cache directory not found." ;fi
      echo "[$(date +'%H:%M:%S')] 🚀 WordPress Local Hot Reload Chain Completed." &
      # docker exec -i wordpress-${WP_ENV} gosu wordpress wp --path=/var/www/html --url=https://lowker.site litespeed-purge all &
      ) &
      pending_pid=$!
    fi
  fi
done
