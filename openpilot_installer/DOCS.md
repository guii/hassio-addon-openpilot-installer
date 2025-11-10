# OpenPilot Installer Generator

This Home Assistant add-on hosts a web interface for generating custom OpenPilot installer binaries.

## Features

- **Dual Installer Support**: Choose between Qt (legacy) and Raylib (modern) installers
- **Custom Fork Support**: Generate installers for any OpenPilot fork
- **Branch Selection**: Install from any branch of your chosen fork
- **Custom Loading Messages**: Personalize the installation experience

## Installer Types

### Qt Installer (Legacy)
- **Use for**: AGNOS versions ≤14.3
- **Dependencies**: Qt5 libraries (included in older AGNOS)
- **UI**: Traditional Qt-based interface
- **File**: `installer_openpilot_agnos`

### Raylib Installer (Modern)
- **Use for**: AGNOS versions >14.3
- **Dependencies**: Raylib graphics library (statically linked)
- **UI**: Modern graphics with smooth animations
- **File**: `installer_openpilot_agnos_raylib`

## Usage

1. **Web Interface**: Navigate to the add-on's web interface
2. **Enter Fork Details**: Provide username, repository, and branch
3. **Choose Installer Type**: Select Qt (legacy) or Raylib (modern) based on your AGNOS version
4. **Download**: Get your custom installer binary

### URL Format

You can also use direct URLs during device setup:

```
http://your-ha-ip:8099/username/repo/branch
```

This will automatically serve the appropriate installer based on the device's user agent.

## Configuration

No additional configuration is required. The add-on will:

1. Build both installer types during container startup
2. Serve the web interface on port 8099
3. Generate custom binaries on demand

## Supported Devices

- **NEOS**: Android-based comma devices
- **AGNOS**: Linux-based comma devices (both legacy and modern versions)

## Technical Details

The add-on uses string replacement to customize pre-compiled installer binaries with:
- GitHub repository URL
- Branch name
- Custom loading message

Both installer types use the same customization mechanism but target different AGNOS versions.