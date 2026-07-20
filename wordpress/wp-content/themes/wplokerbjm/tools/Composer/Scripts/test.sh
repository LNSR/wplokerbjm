#!/usr/bin/env sh
ENV_FILE="../../../../.env"
if [ -f "$ENV_FILE" ]; then
	while IFS= read -r line || [ -n "$line" ]; do
		case "$line" in
			''|\#*)
				continue
				;;
			*=*)
				key=${line%%=*}
				val=${line#*=}
				export "$key=$val"
				;;
		esac
	done < "$ENV_FILE"
fi

docker exec wordpress-${WP_ENV:-production} sh -c "cd /var/www/html/wp-content/themes/wplokerbjm \
&& ./vendor/bin/phpunit --colors=always --fail-on-skipped --testdox"