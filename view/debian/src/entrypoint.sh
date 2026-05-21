#!/bin/bash

# 输出架构信息以便调试
echo "Running on architecture: $(uname -m)"

# 启动 memcached
/etc/init.d/memcached start

# 启动 redis
/etc/init.d/redis-server start

# 启动 php-fpm
# shellcheck disable=SC1083
/etc/init.d/php{VERSION}-fpm start

# 启动 apache2
/etc/init.d/apache2 start

tail -f /var/log/apache2/error.log