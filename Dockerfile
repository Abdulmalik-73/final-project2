FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create writable runtime directories
RUN mkdir -p uploads/ids \
             uploads/payment_screenshots \
             uploads/profiles \
             logs

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/uploads \
 && chmod -R 775 /var/www/html/logs

# Configure Apache virtual host — listen on $PORT (Render sets this)
RUN echo 'ServerName localhost\n\
\n\
# Listen on the PORT env variable (Render requirement)\n\
Listen ${PORT}\n\
\n\
<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    DirectoryIndex index.php index.html\n\
\n\
    <Directory /var/www/html>\n\
        Options FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
\n\
    ErrorLog /dev/stderr\n\
    CustomLog /dev/stdout combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Remove default port 80 Listen directive and replace with env-based one
RUN sed -i 's/^Listen 80$/# Listen 80 (replaced by PORT env var)/' /etc/apache2/ports.conf

# PHP configuration
RUN echo "upload_max_filesize = 10M\n\
post_max_size = 10M\n\
max_execution_time = 300\n\
memory_limit = 256M\n\
display_errors = Off\n\
log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini

# Expose default port (Render overrides with PORT env var at runtime)
EXPOSE 10000

# Start Apache — Render injects PORT at runtime
CMD ["sh", "-c", "export PORT=${PORT:-10000} && apache2-foreground"]
