# Legacy-PHP test runner. Built per version by compose.yaml's `args.PHP_VERSION`.
# Used to run the suite on PHP versions GitHub CI doesn't cover (8.1–8.3).
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

# PHPUnit/Pest need ext-mbstring (not bundled in the base image; mbstring needs
# oniguruma). git + unzip let Composer fetch and extract dist packages.
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libonig-dev \
 && docker-php-ext-install -j"$(nproc)" mbstring \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
