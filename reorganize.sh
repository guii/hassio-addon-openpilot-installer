#!/bin/bash

# This script reorganizes the files to follow Home Assistant's add-on repository structure

# Create the add-on directory if it doesn't exist
mkdir -p openpilot_installer

# Move files to the add-on directory
mv Dockerfile openpilot_installer/
mv config.yaml openpilot_installer/
mv build.yaml openpilot_installer/
mv README.md openpilot_installer/
mv DOCS.md openpilot_installer/
mv icon.txt openpilot_installer/
mv .gitignore openpilot_installer/
mv setup.sh openpilot_installer/

# Move the rootfs directory
mv rootfs openpilot_installer/

# Move GitHub workflows
mkdir -p openpilot_installer/.github/workflows
mv .github/workflows/build.yaml openpilot_installer/.github/workflows/
rmdir .github/workflows
rmdir .github

# Create a new README.md for the repository
cat > README.md << 'EOF'
# Home Assistant Add-on: Openpilot Installer Generator

This repository contains a Home Assistant add-on for the openpilot installer generator.

## Add-ons

This repository contains the following add-ons:

### [Openpilot Installer Generator](./openpilot_installer)

![Supports aarch64 Architecture][aarch64-shield]
![Supports amd64 Architecture][amd64-shield]
![Supports armhf Architecture][armhf-shield]
![Supports armv7 Architecture][armv7-shield]
![Supports i386 Architecture][i386-shield]

Host the openpilot installer generator on your local network through Home Assistant.

## Installation

Follow these steps to add this repository to your Home Assistant instance:

1. Navigate in your Home Assistant frontend to **Settings** -> **Add-ons** -> **Add-on Store**.
2. Click the menu in the top right corner and select **Repositories**.
3. Add the URL `https://github.com/yourusername/hassio-addon-openpilot-installer` and click **Add**.
4. The add-on should now be visible in your add-on store.

## Support

Got questions?

You have several options to get them answered:

- Open an issue here on GitHub
- The Home Assistant [Community Forum][forum]

[aarch64-shield]: https://img.shields.io/badge/aarch64-yes-green.svg
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg
[armhf-shield]: https://img.shields.io/badge/armhf-yes-green.svg
[armv7-shield]: https://img.shields.io/badge/armv7-yes-green.svg
[i386-shield]: https://img.shields.io/badge/i386-yes-green.svg
[forum]: https://community.home-assistant.io/
EOF

echo "Reorganization complete!"
echo "The repository structure now follows Home Assistant's add-on repository requirements."