FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql exif gd mbstring \
    && rm -rf /var/lib/apt/lists/*
RUN printf "upload_max_filesize=8M\npost_max_size=10M\n" > /usr/local/etc/php/conf.d/portfolio-uploads.ini
COPY docker/apache/access-policy.conf /etc/apache2/conf-enabled/zzz-portfolio-access-policy.conf
COPY docker/apache/safe-access-log.conf /etc/apache2/conf-enabled/zzz-portfolio-safe-access-log.conf
COPY docker/apache/development-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2disconf other-vhosts-access-log

WORKDIR /var/www/html

COPY . /var/www/html/
