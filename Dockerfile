FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN printf "upload_max_filesize=8M\npost_max_size=10M\n" > /usr/local/etc/php/conf.d/portfolio-uploads.ini

WORKDIR /var/www/html

COPY . /var/www/html/
