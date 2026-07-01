#!/usr/bin/env zsh
: ${REDIS_PWD:? "Redis Password env missing"} 
: ${WP_ENV:? "WP_ENV env missing"}

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
      sleep 1
  
      echo "[$(date +'%H:%M:%S')] 🚀 Triggering WordPress Local HMR Chain..."
      docker exec -i wordpress-${WP_ENV} pkill -USR2 php-fpm &
      docker exec -i wordpress_redis redis-cli -a ${REDIS_PWD} FLUSHALL
      # docker exec -i wordpress-${WP_ENV} gosu wordpress wp --path=/var/www/html --url=https://lowker.site litespeed-purge all

      echo "[$(date +'%H:%M:%S')] 📦 Dumping Composer Classmap..."
      composer dump-autoload --apcu -a -o > /dev/null
      ) &
      pending_pid=$!
    fi
  fi
done
