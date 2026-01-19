FROM dunglas/frankenphp:1.11.1-php8.4.17-bookworm

ENV XDEBUG_MODE=debug

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
#ENV COMPOSER_ALLOW_SUPERUSER=1

COPY ./.docker/local/php/conf.d/* $PHP_INI_DIR/conf.d/
RUN install-php-extensions \
    @composer \
	intl \
	zip \
    xdebug

RUN setcap -r /usr/local/bin/frankenphp;

