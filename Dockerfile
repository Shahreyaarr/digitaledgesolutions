FROM php:8.2-cli
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql
COPY . /var/www/html/
WORKDIR /var/www/html
EXPOSE 10000
CMD ["php", "-S", "0.0.0.0:10000", "index.php"]
