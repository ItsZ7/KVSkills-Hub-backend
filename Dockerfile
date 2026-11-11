FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql mbstring \
    && a2enmod headers rewrite expires
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage \
    && chmod -R u+rwX,g+rwX /var/www/html/storage
