# 基于官方 PHP 8.5 FPM（Debian Bookworm）。锁只用 Memcached。
FROM php:8.5-fpm-bookworm

# 源码包在构建机里下载。镜像构建时访问 GitHub/PECL 会因证书失败。
COPY docker/php/ext-src/memcached-3.4.0.tar.gz /tmp/memcached.tar.gz
# 安装工具依赖，启用 pdo_mysql、memcached。
# 编译完后清掉 PHPIZE_DEPS 与 apt 缓存，减小镜像体积。
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        openssh-client \
        unzip \
        libzip-dev \
        libmemcached-dev \
        libssl-dev \
        zlib1g-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql \
    && tar -xzf /tmp/memcached.tar.gz -C /tmp \
    && cd /tmp/php-memcached-3.4.0 \
    && phpize \
    && PKG_CONFIG_PATH="/usr/lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH)/pkgconfig" ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable memcached \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/memcached.tar.gz /tmp/php-memcached-3.4.0

# 从官方 composer 镜像拷贝 composer 二进制到本镜像
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 容器内工作目录 = 项目根（与 volume 挂载点一致）
WORKDIR /var/www/html

# 追加本项目 PHP 配置（zz- 前缀保证较晚加载）
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-checkin.ini

# 将 www-data 的 uid/gid 改为 1000，方便宿主机用户读写挂载目录
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
