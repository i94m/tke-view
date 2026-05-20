#!/bin/bash

set -euo pipefail

PHP_FPM_BIN="/usr/sbin/php-fpm{VERSION}"
APACHE_CTL_BIN="/usr/sbin/apache2ctl"

terminate_processes() {
    local signal="${1:-TERM}"

    for pid in "${apache_pid:-}" "${php_fpm_pid:-}" "${memcached_pid:-}"; do
        if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
            kill "-${signal}" "${pid}" 2>/dev/null || true
        fi
    done
}

cleanup() {
    local status="$1"

    terminate_processes TERM

    for pid in "${apache_pid:-}" "${php_fpm_pid:-}" "${memcached_pid:-}"; do
        if [[ -n "${pid}" ]]; then
            wait "${pid}" 2>/dev/null || true
        fi
    done

    exit "${status}"
}

trap 'terminate_processes TERM' INT TERM QUIT

echo "Running on architecture: $(uname -m)"
echo "Starting memcached, php-fpm, and apache2"

memcached -u memcache -m 64 -p 11211 -l 127.0.0.1 &
memcached_pid=$!

"${PHP_FPM_BIN}" -F &
php_fpm_pid=$!

"${APACHE_CTL_BIN}" -D FOREGROUND &
apache_pid=$!

set +e
wait -n "${apache_pid}" "${php_fpm_pid}" "${memcached_pid}"
status=$?
set -e

echo "A managed service exited; shutting down the container."
cleanup "${status}"
