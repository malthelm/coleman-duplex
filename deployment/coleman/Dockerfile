FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libxml2-dev libonig-dev libmcrypt-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j2 pdo_mysql mysqli gd zip soap bcmath mbstring \
 && pecl install mcrypt-1.0.7 && docker-php-ext-enable mcrypt \
 && a2enmod rewrite headers expires
COPY deployment/coleman/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deployment/coleman/php.ini /usr/local/etc/php/conf.d/coleman.ini
COPY . /opt/qloapps
# Listing photographs stay out of the public source repository.
RUN curl --fail --silent --show-error --location --max-time 30 --retry 2 https://photos.zillowstatic.com/fp/ffe06eaa4ebf897140a4c0e189f3870d-cc_ft_960.jpg -o /opt/qloapps/modules/colemanstay/views/img/560-lower-kitchen.jpg \
 && echo "12393c9c4fb1bca7432d412b86c24eed19641cc8942942cee15fb78b23742017  /opt/qloapps/modules/colemanstay/views/img/560-lower-kitchen.jpg" | sha256sum -c - \
 && curl --fail --silent --show-error --location --max-time 30 --retry 2 https://photos.zillowstatic.com/fp/6fe8190736091e984672f1c3c389ad45-cc_ft_960.jpg -o /opt/qloapps/modules/colemanstay/views/img/562-upper-kitchen.jpg \
 && echo "1feef905c11cf9717daf5aab13654413442e7b8edbb4de7607da2f9f503a554b  /opt/qloapps/modules/colemanstay/views/img/562-upper-kitchen.jpg" | sha256sum -c -
COPY deployment/coleman/entrypoint.sh /usr/local/bin/coleman-entrypoint
RUN chmod +x /usr/local/bin/coleman-entrypoint
ENTRYPOINT ["coleman-entrypoint"]
CMD ["apache2-foreground"]
