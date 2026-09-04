FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev libvips-tools \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql exif gd mbstring \
    && rm -rf /var/lib/apt/lists/*
RUN printf "upload_max_filesize=12M\npost_max_size=16M\n" > /usr/local/etc/php/conf.d/portfolio-uploads.ini
ENV VIPS_CONCURRENCY=1 \
    VIPS_BLOCK_UNTRUSTED=1
COPY docker/apache/access-policy.conf /etc/apache2/conf-enabled/zzz-portfolio-access-policy.conf
COPY docker/apache/safe-access-log.conf /etc/apache2/conf-enabled/zzz-portfolio-safe-access-log.conf
COPY docker/apache/development-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2disconf other-vhosts-access-log

WORKDIR /var/www/html

COPY . /var/www/html/
