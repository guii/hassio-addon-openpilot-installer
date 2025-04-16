# Openpilot Installer Generator for Home Assistant

## What is openpilot?

[Openpilot](https://github.com/commaai/openpilot) is an open source driver assistance system developed by comma.ai. It performs the functions of Automated Lane Centering and Adaptive Cruise Control for over 200 supported car makes and models.

## What does this add-on do?

This add-on hosts the openpilot installer generator on your local network through Home Assistant. The installer generator creates custom installers for different GitHub forks of the openpilot project, allowing you to easily install custom versions of openpilot on your comma device.

## Configuration

### Add-on Configuration

```yaml
ssl: false
certfile: fullchain.pem
keyfile: privkey.pem
```

#### Option: `ssl`

Enables/Disables SSL (HTTPS) on the web interface. Set it to `true` to enable it, `false` otherwise.

#### Option: `certfile`

The certificate file to use for SSL.

#### Option: `keyfile`

The private key file to use for SSL.

### Network Configuration

The add-on exposes the web interface on port 8099.

## How to use

1. Start the add-on
2. Open the web interface at `http://your-home-assistant:8099` or click on the "Open Web UI" button in the add-on page
3. Enter the GitHub username and branch of the openpilot fork you want to install
4. Download the installer or use the URL on your comma device during setup

### Using on a comma device

During the setup process on your comma device, you can enter the URL of your installer generator in the following format:

```
http://your-home-assistant:8099/username/branch
```

For example:
```
http://homeassistant.local:8099/commaai/release3
```

This will automatically generate and serve the installer for the specified fork and branch.

### Common forks

- `commaai/release3` - Official openpilot release for comma three
- `commaai/release2` - Official openpilot release for comma two
- `dragonpilot` - DragonPilot fork
- `sunnypilot` - SunnyPilot fork
- `sshane` - Stock Additions fork

## Troubleshooting

### The add-on fails to start

Check the add-on logs for any error messages. Common issues include:

- Port 8099 is already in use by another service
- PHP or Nginx configuration issues

### The web interface is not accessible

- Make sure the add-on is running
- Check that port 8099 is not blocked by your firewall
- Try accessing the web interface through the Home Assistant UI by clicking on the "Open Web UI" button

### The installer fails to download

- Check that the binary files are properly copied to the container
- Ensure the PHP process has permission to read the binary files

## Support

If you have any issues or questions, please open an issue on GitHub.