#!/usr/bin/env sh
docker exec wordpress-${WP_ENV:-production} sh -c "cd /var/www/html/wp-content/themes/wplokerbjm \
&& ./vendor/bin/phpunit --colors=always --fail-on-skipped --testdox"