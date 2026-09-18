FROM php:8.2-apache

# SQLite is de database die de applicatie gebruikt (zie config/database.php)
# "pdo" zelf zit al standaard in de base image; libsqlite3-dev is nodig om de
# sqlite-driver te compileren.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite

# Web root van de applicatie is htdocs/, niet de projectroot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/htdocs
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
