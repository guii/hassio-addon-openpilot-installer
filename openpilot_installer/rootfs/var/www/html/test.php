<?php
// Simple test file to verify PHP is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log access to this test file
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Test file accessed\n", FILE_APPEND);

// Output basic PHP info
echo "<h1>PHP Test Page</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Current Working Directory: " . getcwd() . "</p>";

// Test file permissions
echo "<h2>File Permission Tests:</h2>";
$log_dir = "/var/log/apache2";
$log_file = "/var/log/apache2/debug.log";
$binary_file = __DIR__ . "/fork/installer_openpilot_agnos";

echo "<p>Log directory exists: " . (file_exists($log_dir) ? "Yes" : "No") . "</p>";
echo "<p>Log directory is writable: " . (is_writable($log_dir) ? "Yes" : "No") . "</p>";
echo "<p>Log file exists: " . (file_exists($log_file) ? "Yes" : "No") . "</p>";
echo "<p>Log file is writable: " . (is_writable($log_file) ? "Yes" : "No") . "</p>";
echo "<p>Binary file exists: " . (file_exists($binary_file) ? "Yes" : "No") . "</p>";
echo "<p>Binary file is readable: " . (is_readable($binary_file) ? "Yes" : "No") . "</p>";

// Test loaded modules
echo "<h2>Loaded Apache Modules:</h2>";
echo "<pre>";
$modules = apache_get_modules();
if ($modules) {
    foreach ($modules as $module) {
        echo $module . "\n";
    }
} else {
    echo "Unable to get Apache modules list";
}
echo "</pre>";

// Test PHP modules
echo "<h2>Loaded PHP Modules:</h2>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

// Test environment variables
echo "<h2>Environment Variables:</h2>";
echo "<pre>";
print_r($_ENV);
echo "</pre>";

// Test server variables
echo "<h2>Server Variables:</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
?>