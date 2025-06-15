FROM wordpress:php8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Fix for time synchronization issues and package repositories
RUN mkdir -p /etc/apt/apt.conf.d/ && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/10no-check-valid-until && \
    echo 'APT::Get::AllowUnauthenticated "true";' > /etc/apt/apt.conf.d/10allow-unauthenticated && \
    apt-get update --allow-releaseinfo-change || true && \
    apt-get install -y --no-install-recommends \
    libmagickwand-dev \
    libzip-dev \
    libicu-dev \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install wp-cli - using the stable download URL
RUN curl -o wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug extension
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Imagick extension
RUN cd /tmp \
    && git clone https://github.com/Imagick/imagick.git \
    && cd imagick \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable imagick

# Install most commonly used PHP extensions for WordPress plugins
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libgmp-dev \
    libsodium-dev \
    libonig-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    libmagickwand-dev \
    libxslt1-dev \
    libwebp-dev \
    libxpm-dev \
    libavif-dev \
    libreadline-dev \
    libedit-dev \
    zlib1g-dev \
    libpq-dev \
    libmemcached-dev \
    libzstd-dev \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm --with-avif \
    && docker-php-ext-install -j$(nproc) \
        curl \
        gd \
        mbstring \
        mysqli \
        xml \
        soap \
        gmp \
        pcntl \
        sodium \
    && rm -rf /var/lib/apt/lists/*

# Clean up unnecessary files
RUN apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false

# Copy PHP configuration files
COPY docker.conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker.conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker.conf.d/php.ini /usr/local/etc/php/conf.d/php.ini

# Add WordPress user with your host UID/GID
RUN groupadd -r -g 1000 wordpress && \
    useradd -r -u 1000 -g wordpress wordpress

# Set permissions for WordPress directories
RUN mkdir -p /var/www/html/wp-content/uploads /var/www/html/wp-content/debug

# Ensure wp-cli is accessible for the wordpress user
RUN chmod +x /usr/local/bin/wp && \
    ln -sf /usr/local/bin/wp /usr/bin/wp

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]

USER wordpress
