#!/bin/bash
# ============================================================================
# نقطة دخول الحاوية: تجهيز المجلدات وانتظار قاعدة البيانات ثم تشغيل Apache
# ============================================================================
set -e

# مجلدات الرفع (قد تكون mounted كـ volume فارغ)
mkdir -p /var/www/html/uploads/cars \
         /var/www/html/uploads/avatars \
         /var/www/html/uploads/invoices \
         /var/www/html/assets/images
chown -R www-data:www-data /var/www/html/uploads

# انتظار قاعدة البيانات (حتى 60 ثانية)
if [ -n "${DB_HOST:-}" ] && [ "${DB_HOST}" != "127.0.0.1" ]; then
    echo "⏳ في انتظار قاعدة البيانات ${DB_HOST}:${DB_PORT:-3306} ..."
    for i in $(seq 1 60); do
        if php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; then
            echo "✅ قاعدة البيانات متاحة"
            break
        fi
        sleep 1
    done
fi

exec "$@"
