# 1) Build the Angular frontend (output is platform independent, so build natively)
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npx ng build --configuration production

# 2) PHP + Apache serving API and frontend on one port
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Set by the release workflow; "dev" disables the update check.
ARG VERSION=dev
ARG REPO=
ENV APP_VERSION=$VERSION APP_REPO=$REPO

WORKDIR /var/www/html
COPY backend/ ./
COPY --from=frontend /app/dist/frontend/browser/ ./public/
