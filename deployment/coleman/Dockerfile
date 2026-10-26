FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libxml2-dev libonig-dev libmcrypt-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j2 pdo_mysql mysqli gd zip soap bcmath mbstring \
 && pecl install mcrypt-1.0.7 && docker-php-ext-enable mcrypt \
 && a2enmod rewrite headers expires
COPY deployment/coleman/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deployment/coleman/php.ini /usr/local/etc/php/conf.d/coleman.ini
COPY . /opt/qloapps
COPY deployment/coleman/entrypoint.sh /usr/local/bin/coleman-entrypoint
RUN chmod +x /usr/local/bin/coleman-entrypoint
ENTRYPOINT ["coleman-entrypoint"]
CMD ["apache2-foreground"]
