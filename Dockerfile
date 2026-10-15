# ============================================================================
# Premium Car Rental — صورة PHP + Apache جاهزة
# تعمل محلياً بـ docker-compose، وعلى أي سيرفر يدعم Docker
# (Railway / Render / Fly.io / VPS) بدلاً من الاستضافات Serverless.
# ============================================================================

FROM php:8.3-apache

# ---------------------------------------------------------------------------
# إضافات PHP المطلوبة + mod_rewrite
# ---------------------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        unzip \
        default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli mbstring zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# إعدادات PHP (رفع الملفات، الأخطاء، الجلسات)
# ---------------------------------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/99-car-rental.ini

# ---------------------------------------------------------------------------
# إعداد Apache: السماح بـ .htaccess + رؤوس أمنية
# ---------------------------------------------------------------------------
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/car-rental.conf \
    && a2enconf car-rental \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# ---------------------------------------------------------------------------
# نسخ المشروع
# ---------------------------------------------------------------------------
WORKDIR /var/www/html
COPY . /var/www/html

# مجلدات الرفع (تُحفظ عبر volume في docker-compose) + نقطة الدخول
RUN mkdir -p uploads/cars uploads/avatars uploads/invoices assets/images \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod +x /usr/local/bin/docker-entrypoint-car-rental.sh || true

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint-car-rental.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-car-rental.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-car-rental.sh"]
CMD ["apache2-foreground"]
