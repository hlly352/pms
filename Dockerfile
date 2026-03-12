FROM php:8.2-fpm

# 安装依赖
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev curl \
    && docker-php-ext-install pdo_mysql mbstring zip

# 安装 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# 安装 Laravel 依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]