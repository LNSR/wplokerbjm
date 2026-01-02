#!/usr/bin/env sh
set -a
source ../../../../.env
set +a
docker exec wordpress-${WP_ENV:-production} sh -c "cd /var/www/html/wp-content/themes/wplokerbjm \
&& ./vendor/bin/parallel-lint --colors --exclude vendor --exclude node_modules -j \$(nproc) ."