FROM wordpress:php8.5-fpm AS extension-builder

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update --allow-releaseinfo-change && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    build-essential \
    autoconf \
    make \
    gcc \
    g++ \
    pkg-config \
    zlib1g-dev \
    libzstd-dev \
    libyaml-dev \
    libsasl2-dev \
    libssl-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libgmp-dev \
    libicu-dev \
    libsodium-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libxslt1-dev \
    libwebp-dev \
    libxpm-dev \
    libavif-dev \
    libreadline-dev \
    libedit-dev \
    libbrotli-dev \
    libzip-dev \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -o /usr/local/bin/wp -L https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x /usr/local/bin/wp

RUN curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions

RUN install-php-extensions redis imagick apcu igbinary msgpack yaml brotli bcmath calendar curl exif gd gettext gmp intl mbstring mysqli pcntl soap sockets sodium sysvmsg sysvsem sysvshm xml zip zstd psr fileinfo inotify


FROM extension-builder AS development-builder

RUN install-php-extensions xdebug


FROM wordpress:php8.5-fpm AS runtime-base

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update --allow-releaseinfo-change && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    imagemagick \
    curl \
    jpegoptim \
    optipng \
    pngquant \
    libxpm4 \
    libyaml-0-2 \
    default-jre-headless \
    gosu \
    less \
    cron \
    procps \
    strace \
    && rm -rf /var/lib/apt/lists/*

COPY scripts/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN useradd -r -u 1000 wordpress && \
    usermod -a -G www-data wordpress && \
    chmod +x /usr/local/bin/entrypoint.sh

CMD ["php-fpm"]
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]


FROM runtime-base AS production

COPY --from=extension-builder /usr/local/bin/wp /usr/local/bin/wp
COPY --from=extension-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extension-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker.conf.d/prod/*.ini /usr/local/etc/php/conf.d/
COPY docker.conf.d/prod/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

RUN chmod +x /usr/local/bin/wp && \
    ln -sf /usr/local/bin/wp /usr/bin/wp

RUN php -m || true


FROM runtime-base AS development

COPY --from=development-builder /usr/local/bin/wp /usr/local/bin/wp
COPY --from=development-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=development-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker.conf.d/dev/*.ini /usr/local/etc/php/conf.d/
COPY docker.conf.d/dev/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

RUN chmod +x /usr/local/bin/wp && \
    ln -sf /usr/local/bin/wp /usr/bin/wp

RUN php -m || true
