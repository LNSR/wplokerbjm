#!/bin/bash
set -e

echo "Fixing permissions in /var/www/html..."
chown -R wordpress:wordpress /var/www/html

exec "$@"