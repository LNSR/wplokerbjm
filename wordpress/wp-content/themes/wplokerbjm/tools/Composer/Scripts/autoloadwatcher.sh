#!/usr/bin/env sh
watch_dir=(server tests)

if ! command -v inotifywait >/dev/null 2>&1; then
  echo "Error: inotifywait is not installed. Please install inotify-tools." >&2
  exit 1
fi

for dir in $watch_dir; do
  if [ ! -d "$dir" ]; then
    echo "Error: Watch directory '$dir' does not exist." >&2
    exit 1
  fi
done

# Continuous monitor with debouncing
inotifywait -m -r -e modify,create,delete --format '%w%f' $watch_dir | while read -r file; do
  if [[ -n "$file" ]]; then
    echo "[$(date +'%H:%M:%S')] Change detected in $file, scheduling autoload dump..."
    # Kill any previous pending composer run
    if [[ -n "$pending_pid" ]]; then
      kill "$pending_pid" 2>/dev/null
    fi
    # Run composer in background after a short delay
    (
      sleep 1  # Debounce delay; adjust as needed
      echo "[$(date +'%H:%M:%S')] running composer dump-autoload..."
      composer dump-autoload --apcu -a -o
    ) &
    pending_pid=$!
  fi
done