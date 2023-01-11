FROM php:7.4-cli

RUN apt-get update && apt-get install -y mariadb-client libsqlite3-dev subversion wget git curl zip libzip-dev \
    && docker-php-ext-install iconv \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli

#COMPOSER
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer


WORKDIR /tmp
RUN composer require --dev phpunit/phpunit:~6.5.3 --no-interaction
RUN composer require --dev yoast/phpunit-polyfills --no-interaction
RUN ln -s /tmp/vendor/bin/phpunit /usr/local/bin/phpunit

VOLUME ["/app"]
WORKDIR /app

ENTRYPOINT []

CMD /bin/true