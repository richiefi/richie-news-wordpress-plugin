FROM phpunit/phpunit:6.5.3


RUN apk add --no-cache mariadb-client php7-mysqli php7-iconv subversion wget

RUN apk add gnu-libiconv --update-cache --repository http://dl-cdn.alpinelinux.org/alpine/edge/community/ --allow-untrusted
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

ENTRYPOINT []

CMD /bin/true