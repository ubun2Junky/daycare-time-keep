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

# Copy and configure the entrypoint script
# This script sets permissions AFTER volumes are mounted (at runtime, not build time)
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80 (the default HTTP port)
EXPOSE 80

# Use entrypoint to handle runtime setup (permissions)
ENTRYPOINT ["docker-entrypoint.sh"]

# Start Apache in the foreground
CMD ["apache2-foreground"]
