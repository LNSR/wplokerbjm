#!/usr/bin/env sh
watch_paths=(server composer.json composer.lock)

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
    # Only act on PHP and composer JSON changes to reduce churn
    if [[ "$file" =~ \.(php|json)$ ]]; then
      echo "[$(date +'%H:%M:%S')] Change detected in $file, scheduling autoload dump..."
      # Kill any previous pending composer run
      if [[ -n "$pending_pid" ]]; then
        kill "$pending_pid" 2>/dev/null || true
      fi
      # Run composer in background after a short delay (debounce)
      (
      sleep 2
      echo "[$(date +'%H:%M:%S')] Running composer dump-autoload..." &
      composer dump-autoload --apcu -a -o &
      echo "[$(date +'%H:%M:%S')] Resetting Redis..." &
      docker exec -i wordpress_redis redis-cli --user default -a "redis_secure_password" --no-auth-warning FLUSHALL &
      echo "[$(date +'%H:%M:%S')] Resetting OPcache..." &
      docker exec -i wordpress-${WP_ENV} bash -c "php -r \"opcache_reset();\""
      ) &
      pending_pid=$!
    fi
  fi
done
