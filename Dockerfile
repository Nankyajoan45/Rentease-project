# ─── Stage 1: Base PHP + Apache ───────────────────────────────────────────────
FROM php:8.2-apache

# ─── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── Enable Apache mod_rewrite ────────────────────────────────────────────────
RUN a2enmod rewrite

# ─── Apache virtual host config ───────────────────────────────────────────────
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# ─── PHP config tweaks ────────────────────────────────────────────────────────
RUN echo "upload_max_filesize = 5M"  >> /usr/local/etc/php/conf.d/rentease.ini \
 && echo "post_max_size = 6M"        >> /usr/local/etc/php/conf.d/rentease.ini \
 && echo "max_execution_time = 60"   >> /usr/local/etc/php/conf.d/rentease.ini \
 && echo "memory_limit = 128M"       >> /usr/local/etc/php/conf.d/rentease.ini \
 && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/rentease.ini \
 && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/rentease.ini

# ─── Copy application source ──────────────────────────────────────────────────
COPY . /var/www/html/

# ─── Uploads directory ────────────────────────────────────────────────────────
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# ─── Fix permissions ──────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# ─── Environment variables (overridden at runtime via Railway / .env) ─────────
ENV DB_HOST=mysql.railway.internal \
    DB_PORT=3306 \
    DB_USER=root \
    DB_PASS="" \
    DB_NAME=railway \
    APP_NAME=RentEase \
    APP_URL=http://localhost \
    APP_VERSION=1.0.0

# ─── Expose port ──────────────────────────────────────────────────────────────
EXPOSE 80

# ─── Start Apache in foreground ───────────────────────────────────────────────
CMD ["apache2-foreground"]