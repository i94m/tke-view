FROM debian:13-slim

LABEL author="rt.wadewang"
LABEL description="View local development environment."

WORKDIR /opt/tk

# PHP版本
ARG PHP_VERSION="8.4"
# 声明构建参数
ARG TARGETARCH

# 非交互环境，避免 tzdata 等交互
ENV DEBIAN_FRONTEND=noninteractive

# 合并所有系统操作到一个RUN指令中
RUN useradd -m -d /opt/tk -s /bin/bash -c "TK User" tk && \
    usermod -aG root tk && \
    sed -i 's|http://deb.debian.org|http://mirrors.aliyun.com|g' /etc/apt/sources.list.d/debian.sources && \
    sed -i 's|http://security.debian.org|http://mirrors.aliyun.com|g' /etc/apt/sources.list.d/debian.sources && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        wget \
        tzdata \
        apache2 \
        memcached \
        lsb-release \
        apt-transport-https && \
    update-ca-certificates && \
    ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "deb https://packages.sury.org/php $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-amqp \
        php${PHP_VERSION}-ast \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-bz2 \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-ldap \
        php${PHP_VERSION}-imap \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mcrypt \
        php${PHP_VERSION}-memcache \
        php${PHP_VERSION}-memcached \
        php${PHP_VERSION}-mongodb \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-xmlrpc \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-solr \
        php${PHP_VERSION}-xdebug \
        php${PHP_VERSION}-xhprof \
        php${PHP_VERSION}-zip \
        php-pear && \
    wget https://getcomposer.org/installer -O composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    cp /etc/apache2/mods-available/allowmethods.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/ext_filter.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/include.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/vhost_alias.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/asis.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/expires.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/proxy_fcgi.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/proxy.load /etc/apache2/mods-enabled/ && \
    rm /etc/apache2/sites-enabled/000-default.conf && \
    apt-get autoremove --yes && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /root/.cache /opt/tk/composer-setup.php

# ---------- php.ini 及相关配置 ----------
COPY src/php.ini /etc/php/${PHP_VERSION}/cli/php.ini
COPY src/php.ini /etc/php/${PHP_VERSION}/fpm/php.ini
COPY src/www.conf /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
COPY src/xdebug.ini /etc/php/${PHP_VERSION}/mods-available/xdebug.ini

# 根据架构复制对应的 pdflib 文件
COPY src/php${PHP_VERSION}_pdflib_nts_${TARGETARCH}.so /tmp
RUN PHP_EXTENSION_DIR=$(php -r "echo PHP_EXTENSION_DIR;") && \
    cp /tmp/php${PHP_VERSION}_pdflib_nts_${TARGETARCH}.so ${PHP_EXTENSION_DIR}/pdflib.so
COPY src/pdflib.ini /etc/php/${PHP_VERSION}/cli/conf.d/pdflib.ini
COPY src/pdflib.ini /etc/php/${PHP_VERSION}/fpm/conf.d/pdflib.ini

# 配置 apache2
COPY src/httpd.conf /etc/apache2/apache2.conf
COPY src/sites /etc/apache2/sites-enabled/

# ---------- init ----------
COPY src/entrypoint.sh /run/entrypoint.sh
COPY src/init.sh /run/init.sh
RUN sed -i "s/{VERSION}/${PHP_VERSION}/g" /run/entrypoint.sh && \
    chmod +x /run/entrypoint.sh /run/init.sh

CMD ["/run/entrypoint.sh"]
EXPOSE 80

# 添加架构标签
LABEL org.opencontainers.image.architecture=${TARGETARCH}