FROM php:8.3-fpm

ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache \
    sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuário não-root com o mesmo uid/gid do host, evita arquivos gerados como root.
RUN groupadd -g ${WWWGROUP} appgroup \
    && useradd -u ${WWWUSER} -g appgroup -m -s /bin/bash appuser

WORKDIR /var/www/html

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
