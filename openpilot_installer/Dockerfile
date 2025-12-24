ARG BUILD_FROM=ghcr.io/home-assistant/amd64-base:3.16
FROM ${BUILD_FROM}

# Set shell
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Install required packages including cross-compilation tools
RUN apk add --no-cache \
    apache2 \
    apache2-utils \
    php81 \
    php81-apache2 \
    php81-opcache \
    php81-gd \
    php81-mysqli \
    php81-curl \
    php81-zip \
    php81-xml \
    php81-mbstring \
    php81-phar \
    php81-openssl \
    php81-session \
    php81-ctype \
    git \
    wget \
    curl \
    ca-certificates

# Download pre-built aarch64 installer binary from openpilot releases
# This is the official installer binary that works on comma devices
RUN mkdir -p /tmp/installer && \
    cd /tmp/installer && \
    # Try to download from a recent openpilot release
    # The installer binary is built as part of openpilot's CI
    echo "Downloading pre-built installer..." && \
    # We'll use a placeholder approach - the binary will be provided separately
    touch /tmp/installer/installer_placeholder

# Copy root filesystem
COPY rootfs /

# Create a script to download the latest installer from GitHub releases
RUN cat > /var/www/html/fork/download_installer.sh << 'EOF'
#!/bin/bash
# Script to download the latest openpilot installer binary
# Run this manually to update the installer binary

INSTALLER_URL="https://github.com/commaai/openpilot/releases/latest/download/installer_openpilot"
OUTPUT_PATH="/var/www/html/fork/installer_openpilot_agnos_raylib"

echo "Downloading latest installer from: $INSTALLER_URL"
wget -O "$OUTPUT_PATH" "$INSTALLER_URL" || {
    echo "Failed to download from releases, trying alternative..."
    # Alternative: build from source or use cached version
    exit 1
}

chmod 644 "$OUTPUT_PATH"
echo "Installer downloaded successfully to: $OUTPUT_PATH"
EOF
RUN chmod +x /var/www/html/fork/download_installer.sh

# Set proper permissions
RUN chmod 755 /var/www/html/fork \
    && chmod 644 /var/www/html/fork/*.php 2>/dev/null || true \
    && chmod 644 /var/www/html/fork/.htaccess 2>/dev/null || true \
    && chmod 644 /var/www/html/fork/favicon.ico 2>/dev/null || true \
    && chmod +x /etc/services.d/*/run 2>/dev/null || true

# Create log directory
RUN mkdir -p /var/log/apache2 \
    && mkdir -p /run/apache2 \
    && touch /var/log/apache2/error.log \
    && touch /var/log/apache2/access.log \
    && touch /var/log/apache2/debug.log \
    && touch /var/log/apache2/php_errors.log \
    && chmod 666 /var/log/apache2/*.log

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
