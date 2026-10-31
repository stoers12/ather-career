# One image definition for every runtime (docs/RUNTIME_TOPOLOGY.md).
# Code lives in /var/www/app; only the repository public/ tree is copied to
# the Apache document root /var/www/public, so nothing else is web-reachable.
# Targets: development (dev Compose and the required gates) and production
# (the default; CI, production and the Owner's local stack run this image).

FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-autoloader
# Packages stay cached; only the class map is rebuilt when src/ changes.
COPY src ./src
RUN composer dump-autoload --no-dev --optimize --no-interaction

# The development target also carries the dev tools (PHPUnit, PHPStan) so the
# required gates can run them; the production target never does.
FROM dependencies AS dev-dependencies
RUN composer install --prefer-dist --no-interaction --no-autoloader \
    && composer dump-autoload --optimize --no-interaction

FROM php:8.3-apache AS runtime-base

ENV VIPS_CONCURRENCY=1 \
    VIPS_BLOCK_UNTRUSTED=1

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends default-mysql-client openssl libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev libvips-tools \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql exif gd mbstring intl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers rewrite unique_id

COPY docker/apache/access-policy.conf /etc/apache2/conf-enabled/zzz-portfolio-access-policy.conf
COPY docker/apache/security-headers.conf /etc/apache2/conf-enabled/zzz-portfolio-security-headers.conf
COPY docker/apache/cache-policy.conf /etc/apache2/conf-enabled/zzz-portfolio-cache-policy.conf
COPY docker/apache/performance.conf /etc/apache2/conf-enabled/zzz-portfolio-performance.conf
COPY docker/apache/safe-access-log.conf /etc/apache2/conf-enabled/zzz-portfolio-safe-access-log.conf
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/production-entrypoint.sh /usr/local/bin/portfolio-production-entrypoint
COPY docker/maintenance-entrypoint.sh /usr/local/bin/portfolio-maintenance-entrypoint
COPY docker/validate-oidc-issuer.sh /usr/local/bin/validate-oidc-issuer.sh
RUN a2disconf other-vhosts-access-log \
    && chmod 755 /usr/local/bin/portfolio-production-entrypoint /usr/local/bin/portfolio-maintenance-entrypoint

WORKDIR /var/www/app

FROM runtime-base AS development

RUN printf "upload_max_filesize=12M\npost_max_size=16M\n" > /usr/local/etc/php/conf.d/portfolio-uploads.ini

# Self-contained for the required gates; dev Compose bind-mounts the checkout
# over /var/www/app and /var/www/public so edits are live.
COPY . /var/www/app/
COPY --from=dev-dependencies /app/vendor /var/www/app/vendor
RUN mkdir -p /var/www/public /var/www/app/runtime/rate-limit \
    && cp -a /var/www/app/public/. /var/www/public/ \
    && chown -R www-data:www-data /var/www/app/runtime

ENTRYPOINT ["/usr/local/bin/portfolio-production-entrypoint"]
CMD ["apache2-foreground"]

FROM runtime-base AS production

ARG APP_VERSION=unknown
LABEL org.opencontainers.image.revision="${APP_VERSION}"
ENV ATHERCAR_STORAGE_ROOT=/var/lib/ather-career/storage \
    PORTFOLIO_PRODUCTION_SECURITY_CHECK=1

COPY docker/php/production.ini /usr/local/etc/php/conf.d/portfolio-production.ini

COPY . /var/www/app/
COPY --from=dependencies /app/vendor /var/www/app/vendor
RUN mkdir -p /var/www/public /var/www/app/runtime/rate-limit /var/lib/ather-career/storage \
    && cp -a /var/www/app/public/. /var/www/public/ \
    && chown -R www-data:www-data /var/www/app/runtime /var/lib/ather-career/storage

ENTRYPOINT ["/usr/local/bin/portfolio-production-entrypoint"]
CMD ["apache2-foreground"]
