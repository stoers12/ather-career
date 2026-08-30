FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql exif gd \
    && rm -rf /var/lib/apt/lists/*
RUN printf "upload_max_filesize=8M\npost_max_size=10M\n" > /usr/local/etc/php/conf.d/portfolio-uploads.ini
COPY docker/apache/access-policy.conf /etc/apache2/conf-enabled/zzz-portfolio-access-policy.conf

WORKDIR /var/www/html

COPY . /var/www/html/
