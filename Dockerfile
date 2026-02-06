FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy all files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Create .htaccess for URL rewriting
RUN echo 'RewriteEngine On\n\
RewriteCond %{REQUEST_FILENAME} !-f\n\
RewriteCond %{REQUEST_FILENAME} !-d\n\
RewriteRule ^api/(.*)$ backend/api/index.php?path=$1 [QSA,L]\n\
RewriteRule ^(.*)$ frontend/public/$1 [L]' > /var/www/html/.htaccess

# Expose port
EXPOSE 10000

# Start PHP server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
