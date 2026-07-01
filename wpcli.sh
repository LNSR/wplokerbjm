set -a
source .env 2>/dev/null || true  # Ignore if .env missing
set +a

alias wpcli='docker exec wordpress-${WP_ENV} gosu wordpress php -d opcache.jit=off -d error_reporting="E_ALL & ~E_DEPRECATED" /usr/local/bin/wp --path=/var/www/html --url=https://lowker.site'