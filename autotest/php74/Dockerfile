FROM debian:13-slim

LABEL author="rt.wadewang"
LABEL description="View autotesting environment."

WORKDIR /opt/autotest

# PHP版本
ARG PHP_VERSION="7.4"
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
        lsb-release \
        apt-transport-https && \
    update-ca-certificates && \
    ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "deb https://packages.sury.org/php $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        php${PHP_VERSION} \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-bz2 \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-ldap \
        php${PHP_VERSION}-imap \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mcrypt \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-xdebug \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-xmlrpc \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-xhprof \
        php${PHP_VERSION}-zip \
        php-pear && \
    apt-get autoremove --yes && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /root/.cache

# ---------- php.ini 及相关配置 ----------
COPY src/php.ini /etc/php/${PHP_VERSION}/cli/php.ini
COPY src/xdebug.ini /etc/php/${PHP_VERSION}/mods-available/xdebug.ini

# ---------- behat ----------
COPY src/behat-3.12.0.phar /usr/bin/behat

# ---------- phpunit ----------
COPY src/phpunit-9.6.31.phar /usr/bin/phpunit

# ---------- init ----------
COPY src/entrypoint.sh /run/entrypoint.sh
COPY src/init.sh /run/init.sh
RUN chmod +x /usr/bin/behat && \
    chmod +x /usr/bin/phpunit && \
    sed -i "s/{VERSION}/${PHP_VERSION}/g" /run/entrypoint.sh && \
    chmod +x /run/entrypoint.sh /run/init.sh && \
    touch /var/log/error.log

CMD ["/run/entrypoint.sh"]
EXPOSE 80

# 添加架构标签
LABEL org.opencontainers.image.architecture=${TARGETARCH}