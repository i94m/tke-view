#!/bin/sh

set -eu

local_cmd="${VIEW_LOCAL_HEALTH_LOCAL_CMD:-/usr/local/bin/view-local}"
app_root="${VIEW_LOCAL_HEALTH_APP_ROOT:-${VIEW_APP_ROOT:-/opt/tk/core}}"
site_root_base="${VIEW_LOCAL_HEALTH_SITE_ROOT_BASE:-${VIEW_SITE_ROOT_BASE:-/opt/sites}}"
dev_config="${VIEW_LOCAL_HEALTH_DEV_CONFIG:-${VIEW_DEV_CONFIG:-/opt/dev-config/config.env}}"
default_site="${VIEW_LOCAL_DEFAULT_SITE:-}"

if [ -z "$default_site" ] && [ -f "$dev_config" ]; then
	default_site="$(sed -n 's/^VIEW_LOCAL_DEFAULT_SITE=//p' "$dev_config" | head -n 1 | tr -d '"' | tr -d "'")"
fi

pidof apache2 >/dev/null 2>&1
pidof php-fpm{VERSION} >/dev/null 2>&1
pidof memcached >/dev/null 2>&1

[ -n "$default_site" ]
[ -r "$app_root/vivid/tests/bootstrap.php" ]
[ -r "$app_root/vivid/phpunit.xml" ]
[ -r "$app_root/ViewLoggerConfig.php" ]
[ -r "$app_root/log4php_config.xml" ]
[ -r "$site_root_base/$default_site/config.php" ]

"$local_cmd" diagnose --site "$default_site" --format text >/dev/null 2>&1
