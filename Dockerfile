FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        libsqlite3-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) gd intl zip pdo pdo_sqlite opcache \
    && a2enmod rewrite \
    && printf '\nServerName localhost\n' >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 80

CMD ["apache2-foreground"]
