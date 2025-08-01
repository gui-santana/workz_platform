# .docker/php/Dockerfile

FROM php:7.4-fpm-alpine

WORKDIR /var/www/html

# Instala pacotes e extensões necessárias
RUN apk add --no-cache \
    bzip2-dev \
    unzip \
    zip \
    curl \
    git \
    && docker-php-ext-install pdo pdo_mysql bz2

# Baixa e instala o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Limpa o cache do gerenciador de pacotes
RUN rm -rf /var/cache/apk/*
