#!/bin/bash
# Docker entrypoint script
# This runs AFTER volumes are mounted, so we can set permissions correctly

# Fix permissions on the data directory for the web server
# This is necessary when running on Linux systems (like Raspberry Pi)
chown -R www-data:www-data /var/www/html/data 2>/dev/null || true
chmod -R 775 /var/www/html/data 2>/dev/null || true

# Execute the CMD from Dockerfile (start Apache)
exec "$@"
