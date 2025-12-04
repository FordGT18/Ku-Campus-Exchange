# Author: Samar Gill
# File: Dockerfile
# Description: Dockerfile for PHP and Postgres container setup
FROM php:8.2-fpm

# Install dependencies for PostgreSQL + GD (for image resizing)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    zlib1g-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
