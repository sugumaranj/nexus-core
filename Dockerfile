# Use the official PHP 8.5 image with Apache pre-installed
FROM php:8.5-apache

# Install required system packages and PHP extensions
# - git, unzip, libzip-dev: Required for Composer to work properly
# - libfreetype6-dev, libjpeg62-turbo-dev, libpng-dev, libwebp-dev: Required for full GD image processing
# - pdo_mysql, mysqli: Required to connect to a MySQL database
# - opcache: Bytecode caching for optimal performance
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip opcache

# Enable Apache's mod_rewrite module, which is necessary if you use .htaccess for URL routing
RUN a2enmod rewrite

# Set the working directory inside the container
WORKDIR /var/www/html

# Get Composer from its official image and put it into our container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the rest of your application files into the container's working directory
COPY . /var/www/html/

# Set the correct permissions so Apache can read and write to the files
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 to the outside world
EXPOSE 80
