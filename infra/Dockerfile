FROM commander15/laravel:13-cli

# Temporarly switching to root
USER root

# Install the exact extensions
RUN install-php-extensions pdo_pgsql redis

# Switching back to laravel user
USER laravel

# Installing dependecies
COPY --chown=laravel:laravel composer.json composer.lock ./

# Installing composer dependencies
RUN composer-install

# Copying application files
COPY --chown=laravel:laravel . .

# Optimizing dependencies and caching
RUN composer-optimize

# Running server
CMD ["php", "artisan", "serve", "--host", "0.0.0.0"]
