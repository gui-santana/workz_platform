# .docker/php/Dockerfile

FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Instala pacotes e extensões necessárias
RUN apk add --no-cache \
    bzip2-dev \
    unzip \
    zip \
    curl \
    curl-dev \
    ca-certificates \
    git \
    && update-ca-certificates \
    && docker-php-ext-install pdo pdo_mysql bz2 curl

# Baixa e instala o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Limpa o cache do gerenciador de pacotes
RUN rm -rf /var/cache/apk/*

# Configura limites de upload do PHP
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
