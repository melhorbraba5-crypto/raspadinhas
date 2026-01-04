# Dockerfile
FROM php:8.2-apache-bullseye

# Habilitar mod_rewrite e instalar dependências
RUN a2enmod rewrite \
    && apt-get update && apt-get install -y \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# ✅ NOVO: Copia a configuração personalizada do Apache para apontar para a pasta /public
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Instalar o Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Definir o diretório de trabalho
WORKDIR /var/www/html

# Copiar e instalar dependências
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copiar o restante da aplicação
COPY . .

# Definir permissões
RUN chown -R www-data:www-data /var/www/html

# Expor a porta e iniciar o servidor
EXPOSE 80
CMD ["apache2-foreground"]