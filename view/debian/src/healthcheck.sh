#!/bin/sh

set -eu

pidof apache2 >/dev/null 2>&1
pidof php-fpm{VERSION} >/dev/null 2>&1
pidof memcached >/dev/null 2>&1
