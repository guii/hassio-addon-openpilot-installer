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

# Update repository.yaml with the correct username
sed -i.bak "s|yourusername|$github_username|g" repository.yaml
rm repository.yaml.bak

# Update README.md with the correct username
sed -i.bak "s|yourusername|$github_username|g" README.md
rm README.md.bak

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

# Initialize git repository if not already initialized
if [ ! -d ".git" ]; then
    echo ""
    echo "Initializing git repository..."
    git init
fi

# Add all files
git add .

# Commit changes
git commit -m "Initial commit of Openpilot Installer Generator add-on"

# Set the branch name to main if not already set
current_branch=$(git symbolic-ref --short HEAD 2>/dev/null || echo "")
if [ "$current_branch" != "main" ]; then
    git branch -M main
fi

# Add remote if not already added
remote_url=$(git remote get-url origin 2>/dev/null || echo "")
if [ "$remote_url" != "https://github.com/$github_username/hassio-addon-openpilot-installer.git" ]; then
    git remote remove origin 2>/dev/null || true
    git remote add origin "https://github.com/$github_username/hassio-addon-openpilot-installer.git"
fi

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