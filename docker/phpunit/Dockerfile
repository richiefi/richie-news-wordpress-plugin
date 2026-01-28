ARG PHP_VERSION=8.0
FROM php:${PHP_VERSION}-cli

RUN apt-get update && apt-get install -y mariadb-client libsqlite3-dev subversion wget git curl zip libzip-dev \
    && docker-php-ext-install iconv \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli

# Install PCOV for code coverage
RUN pecl install pcov && docker-php-ext-enable pcov

#COMPOSER
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer


WORKDIR /opt/phpunit
RUN composer require --no-interaction --prefer-dist --no-progress --no-suggest \
    "phpunit/phpunit:^9" "yoast/phpunit-polyfills:^2"

RUN ln -sf /opt/phpunit/vendor/bin/phpunit /usr/local/bin/phpunit

VOLUME ["/app"]
WORKDIR /app

ENTRYPOINT []

CMD /bin/true