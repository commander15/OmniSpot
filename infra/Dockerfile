FROM php:8.5-cli-alpine

# Set the working directory inside the container
WORKDIR /var/www

# Install core system tools needed for running the container
RUN apk add --no-cache bash curl zip unzip git

# Download the script to automatically install and configure PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Copy Composer from its official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install the exact extensions you requested
RUN install-php-extensions bcmath gd zip redis pdo_pgsql pcntl

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies without running Laravel's autoload/scripts 
# (Since the actual app code isn't inside the container yet)
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --no-dev \
    --prefer-dist \
    --no-autoloader

# Copy the rest of your application code
COPY . .

# Run composer dump-autoload to generate the final optimized autoloader 
# now that the application code is present.
RUN composer dump-autoload --no-dev --optimize

# Set correct permissions for Laravel's writable directories
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Set Entry Point
COPY infra/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# Expose port 8000 (artisan serve) and port 8080 (Laravel Reverb)
EXPOSE 8000 8080

# Bind to 0.0.0.0 so the server can accept requests from outside the container
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
