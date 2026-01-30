set -a
source .env 2>/dev/null || true  # Ignore if .env missing
set +a
alias wpcli='docker exec wordpress-${WP_ENV} gosu wordpress wp --path=/var/www/html --url=caddy:443'