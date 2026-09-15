# Use the official PHP 8.2 image with Apache pre-installed
FROM php:8.2-apache

# Install required system packages and PHP extensions
# - git, unzip, libzip-dev: Required for Composer to work properly
# - libpng-dev, gd: Helpful for PDF/Image processing (dompdf, fpdf)
# - pdo_mysql, mysqli: Required to connect to a MySQL database
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd

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
