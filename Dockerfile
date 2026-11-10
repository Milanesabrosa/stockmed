FROM php:8.2-apache

# Instalar extensiones necesarias para CodeIgniter y MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite de Apache para las URLs amigables
RUN a2enmod rewrite

# Copiar los archivos del proyecto al servidor web de Apache
COPY . /var/www/html/

# Configurar permisos de la carpeta writable de CodeIgniter
RUN chown -R www-data:www-data /var/www/html/writable \
    && chmod -R 775 /var/www/html/writable

# Cambiar el DocumentRoot de Apache a la carpeta public de CodeIgniter
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
documents:
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html/public!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf