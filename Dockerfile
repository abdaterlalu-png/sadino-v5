FROM php:8.3-apache
LABEL org.opencontainers.image.title="SADINO Financial Agent" org.opencontainers.image.version="5.0.0"
RUN apt-get update && apt-get install -y --no-install-recommends curl libonig-dev mariadb-client ca-certificates \
 && docker-php-ext-install pdo_mysql mbstring \
 && a2enmod rewrite headers expires \
 && rm -rf /var/lib/apt/lists/*
RUN printf '%s\n' \
 'file_uploads=On' 'upload_max_filesize=16M' 'post_max_size=20M' 'max_file_uploads=5' \
 'memory_limit=256M' 'max_execution_time=90' 'expose_php=Off' 'display_errors=Off' 'log_errors=On' \
 'session.save_path=/var/www/html/storage/sessions' 'session.use_strict_mode=1' \
 > /usr/local/etc/php/conf.d/sadino-production.ini \
 && printf '%s\n' 'ServerTokens Prod' 'ServerSignature Off' > /etc/apache2/conf-available/sadino-security.conf \
 && a2enconf sadino-security
WORKDIR /var/www/html
COPY . /var/www/html
RUN find app cli public -type f -name '*.php' -exec php -l {} \; >/tmp/php-lint.log
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && mkdir -p storage/uploads storage/monitor storage/sessions storage/logs backups \
 && chown -R www-data:www-data storage \
 && chmod -R 0750 storage
COPY docker-entrypoint-sadino.sh /usr/local/bin/docker-entrypoint-sadino
RUN chmod 0755 /usr/local/bin/docker-entrypoint-sadino
EXPOSE 80
ENTRYPOINT ["docker-entrypoint-sadino"]
CMD ["apache2-foreground"]
