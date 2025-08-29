FROM wordpress:php8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Fix for time synchronization issues and package repositories
RUN mkdir -p /etc/apt/apt.conf.d/ && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/10no-check-valid-until && \
    echo 'APT::Get::AllowUnauthenticated "true";' > /etc/apt/apt.conf.d/10allow-unauthenticated

# Install all system dependencies
RUN apt-get update --allow-releaseinfo-change || true && \
    apt-get install -y --no-install-recommends \
    libmagickwand-dev \
    libzip-dev \
    libicu-dev \
    git \
    curl \
    pkg-config \
    libmemcached-dev \
    zlib1g-dev \
    libzstd-dev \
    libyaml-dev \
    libsasl2-dev \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libgmp-dev \
    libsodium-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libxslt1-dev \
    libwebp-dev \
    libxpm-dev \
    libavif-dev \
    libreadline-dev \
    libedit-dev \
    libpq-dev \
    libmcrypt-dev \
    libbrotli-dev \
    gosu \
    less \
    && rm -rf /var/lib/apt/lists/*

# Install wp-cli
RUN curl -o wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Install and enable PECL extensions
RUN pecl install redis xdebug imagick apcu memcached igbinary msgpack yaml brotli && \
    docker-php-ext-enable redis xdebug imagick apcu memcached igbinary msgpack yaml brotli

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm --with-avif && \
    docker-php-ext-install -j$(nproc) \
    bcmath \
    calendar \
    curl \
    exif \
    gd \
    gettext \
    gmp \
    intl \
    mbstring \
    mysqli \
    opcache \
    pcntl \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    soap \
    sockets \
    sodium \
    sysvmsg \
    sysvsem \
    sysvshm \
    xml \
    zip

# Clean up unnecessary files
RUN apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false

# Copy PHP configuration files
COPY docker.conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker.conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker.conf.d/php.ini /usr/local/etc/php/conf.d/php.ini

# Add WordPress user and setup wp-cli
RUN useradd -r -u 1000 wordpress && \
    usermod -a -G www-data wordpress && \
    chmod +x /usr/local/bin/wp && \
    ln -sf /usr/local/bin/wp /usr/bin/wp

CMD ["php-fpm"]
COPY scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT [ "/usr/local/bin/entrypoint.sh" ]