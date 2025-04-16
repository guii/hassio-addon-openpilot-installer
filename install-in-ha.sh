#!/bin/bash

# This script helps with the installation of the openpilot-installer-generator add-on in Home Assistant

echo "Openpilot Installer Generator Add-on Installation Helper"
echo "========================================================"
echo ""

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "Error: git is not installed. Please install git first."
    exit 1
fi

# Ask for GitHub username
read -p "Enter your GitHub username: " github_username
if [ -z "$github_username" ]; then
    echo "Error: GitHub username cannot be empty."
    exit 1
fi

echo ""
echo "This script will help you create a GitHub repository for the add-on and provide"
echo "instructions for adding it to Home Assistant."
echo ""
echo "Steps:"
echo "1. Create a new GitHub repository named 'hassio-addon-openpilot-installer'"
echo "2. Push the add-on files to the repository"
echo "3. Add the repository to Home Assistant"
echo ""
read -p "Press Enter to continue or Ctrl+C to cancel..."

# Create GitHub repository
echo ""
echo "Creating GitHub repository..."
echo "Please create a new repository on GitHub named 'hassio-addon-openpilot-installer'"
echo "Visit: https://github.com/new"
echo ""
read -p "Press Enter when you've created the repository..."

# Initialize git repository
echo ""
echo "Initializing git repository..."
cd "$(dirname "$0")"
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin "https://github.com/$github_username/hassio-addon-openpilot-installer.git"

echo ""
echo "Ready to push to GitHub. Please make sure you have set up authentication."
read -p "Press Enter to push to GitHub or Ctrl+C to cancel..."
git push -u origin main

echo ""
echo "Add-on files pushed to GitHub!"
echo ""
echo "To add this repository to Home Assistant:"
echo "1. Go to Settings → Add-ons → Add-on Store"
echo "2. Click the menu in the top right corner"
echo "3. Select 'Repositories'"
echo "4. Add the URL: https://github.com/$github_username/hassio-addon-openpilot-installer"
echo "5. Click 'Add'"
echo "6. Find the 'Openpilot Installer Generator' add-on and click 'Install'"
echo ""
echo "Installation helper completed!"