# Openpilot Installer Generator for Home Assistant

This add-on allows you to host the openpilot installer generator on your local network through Home Assistant.

## About

The openpilot installer generator creates custom installers for different GitHub forks of the openpilot project. This add-on makes it easy to host this service on your local network through Home Assistant.

## Features

- Generate custom openpilot installers for different GitHub forks
- Support for both NEOS (Android-based) and AGNOS platforms
- Specify custom branches and loading messages
- Easy-to-use web interface
- Integrated with Home Assistant

## Installation

1. Add this repository to your Home Assistant instance:
   - Go to Settings → Add-ons → Add-on Store
   - Click the menu in the top right corner
   - Select "Repositories"
   - Add the URL: `https://github.com/yourusername/hassio-addon-openpilot-installer`
   - Click "Add"

2. Find the "Openpilot Installer Generator" add-on and click "Install"

3. Start the add-on

4. Access the web interface at:
   - http://your-home-assistant:8099
   - Or through the Home Assistant UI by clicking on the add-on's "Open Web UI" button

## Usage

1. Enter the GitHub username and branch of the openpilot fork you want to install
2. Download the installer or use the URL on your comma device during setup

## Support

If you have any issues or questions, please open an issue on GitHub.

## License

This project is licensed under the same license as the original openpilot-installer-generator.

## Credits

This add-on is based on the [openpilot-installer-generator](https://github.com/sshane/openpilot-installer-generator) by Shane Smiskol.