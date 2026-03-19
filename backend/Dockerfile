FROM php:8.2-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    curl \
    # --- 新增 GD 库所需的系统依赖 ---
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- 配置并安装 PHP 扩展 ---
# 注意：gd 扩展需要先 configure 配置，否则可能不支持 jpeg 或 freetype
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring zip gd

# 安装 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www


# 这样即便在构建镜像时，也能享受更稳定的下载链路
RUN composer config -g repo.packagist composer https://mirrors.cloud.tencent.com/composer/ \
    && composer config -g process-timeout 3600

COPY . .

# 安装 Laravel 依赖
#RUN composer install --no-dev --optimize-autoloader --no-scripts #注释是为了防止联网报错

# 设置权限
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
