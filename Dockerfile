FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install fileinfo extension (needed for ID upload MIME validation)
RUN docker-php-ext-enable fileinfo || true

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create writable directories that are excluded from git
RUN mkdir -p uploads/ids \
             uploads/payment_screenshots \
             uploads/profiles \
             logs

# Set proper permissions — www-data needs write access to uploads and logs
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/uploads \
 && chmod -R 775 /var/www/html/logs

# Configure Apache to serve from root
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf && \
    a2enconf app

# Set PHP configuration
RUN echo "upload_max_filesize = 10M\n\
post_max_size = 10M\n\
max_execution_time = 300\n\
memory_limit = 256M\n\
display_errors = Off\n\
log_errors = On\n\
error_log = /var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/custom.ini

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
