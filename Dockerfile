FROM php:7.4-apache

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

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
memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
