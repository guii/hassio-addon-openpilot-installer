#!/bin/bash

# This script copies the necessary files from the original openpilot-installer-generator
# to the Home Assistant add-on directory structure

# Create directories if they don't exist
mkdir -p rootfs/var/www/html/fork
mkdir -p rootfs/var/www/html/source

# Copy the original files
echo "Copying original files..."
cp -r ../fork/* rootfs/var/www/html/fork/
cp -r ../source/* rootfs/var/www/html/source/

# Replace index.php with our modified version
echo "Using modified index.php..."
# The modified index.php is already in place

# Set permissions
echo "Setting permissions..."
chmod 755 rootfs/var/www/html/fork
chmod 644 rootfs/var/www/html/fork/installer_openpilot_*
chmod 644 rootfs/var/www/html/fork/*.php
chmod 644 rootfs/var/www/html/fork/.htaccess
chmod 644 rootfs/var/www/html/fork/favicon.ico
chmod +x rootfs/etc/services.d/*/run

echo "Setup complete!"
echo "You can now build the add-on with:"
echo "  docker build -t openpilot-installer-hassio ."
echo "Or push it to GitHub and install it in Home Assistant."