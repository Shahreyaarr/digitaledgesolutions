FROM php:8.2-cli

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy all files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 10000

# Start PHP server with router
CMD ["php", "-S", "0.0.0.0:10000", "index.php"]
