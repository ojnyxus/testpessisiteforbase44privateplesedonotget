FROM php:8.2-apache

# Apache: serve the app from public/ and let the front controller handle routes
# (/library, /asset/{slug}, /api/... are not real files).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
 && apt-get install -y --no-install-recommends curl \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri -e "s!/var/www/html!\${APACHE_DOCUMENT_ROOT}!g" \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf \
 && printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '    Options -Indexes' \
      '    AllowOverride All' \
      '    Require all granted' \
      '    FallbackResource /index.php' \
      '</Directory>' \
      > /etc/apache2/conf-available/base44-app.conf \
 && a2enconf base44-app \
 && printf '%s\n' \
      'display_errors=On' \
      'error_reporting=E_ALL' \
      'opcache.enable=0' \
      'upload_max_filesize=32M' \
      > /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
