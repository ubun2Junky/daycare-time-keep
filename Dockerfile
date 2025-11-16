# Daycare Timekeeper Dockerfile
#
# This file tells Docker how to build a container for your application.
# It uses PHP 8.2 with Apache web server.

# Start with official PHP 8.2 with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy all application files to the container
COPY . /var/www/html/

# Configure Apache to use the 'public' folder as the document root
# This means Apache will serve files from /public instead of the root
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Enable Apache mod_rewrite (useful for clean URLs if needed later)
RUN a2enmod rewrite

# Set proper permissions for the data directory
# www-data is the user Apache runs as
# 775 means: owner can read/write/execute, group can read/write/execute, others can read/execute
RUN chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

# Expose port 80 (the default HTTP port)
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
