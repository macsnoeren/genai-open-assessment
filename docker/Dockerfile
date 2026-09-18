FROM php:8.2-apache

# SQLite is de database die de applicatie gebruikt (zie config/database.php)
# "pdo" zelf zit al standaard in de base image; libsqlite3-dev is nodig om de
# sqlite-driver te compileren.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite

# Productie-instellingen voor PHP: geen foutmeldingen/stack traces naar de browser
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -ri -e 's/^expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini"

# Web root van de applicatie is htdocs/, niet de projectroot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/htdocs
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    # Geen directory listings, geen serverversie in headers/foutpagina's
    && sed -ri -e 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/apache2/apache2.conf \
    && sed -ri -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
