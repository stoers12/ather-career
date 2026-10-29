# CI coverage only: the development image plus the PCOV driver. Never deployed.
ARG BASE_IMAGE
FROM ${BASE_IMAGE}
RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
