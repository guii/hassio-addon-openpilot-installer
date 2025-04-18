ARG BUILD_FROM=ghcr.io/home-assistant/aarch64-base:3.16
FROM ${BUILD_FROM}

# Set shell
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Install required packages
RUN apk add --no-cache \
    apache2 \
    apache2-utils \
    php8 \
    php8-apache2 \
    php8-opcache \
    php8-gd \
    php8-mysqli \
    php8-curl \
    php8-zip \
    php8-xml \
    php8-mbstring \
    php8-phar \
    php8-openssl \
    php8-session \
    php8-ctype \
    git

# Copy root filesystem
COPY rootfs /

# Set proper permissions
RUN chmod 755 /var/www/html/fork \
    && chmod 644 /var/www/html/fork/installer_openpilot_* \
    && chmod 644 /var/www/html/fork/*.php \
    && chmod 644 /var/www/html/fork/.htaccess \
    && chmod 644 /var/www/html/fork/favicon.ico \
    && chmod +x /etc/services.d/*/run

# Create log directory
RUN mkdir -p /var/log/apache2 \
    && mkdir -p /run/apache2 \
    && touch /var/log/apache2/error.log \
    && touch /var/log/apache2/access.log

# Labels
LABEL \
    io.hass.name="Openpilot Installer Generator" \
    io.hass.description="Host the openpilot installer generator on your local network" \
    io.hass.version="${BUILD_VERSION}" \
    io.hass.type="addon" \
    io.hass.arch="armhf|aarch64|i386|amd64"

# Expose the web interface
EXPOSE 8099

# Start services
ENTRYPOINT ["/init"]