<?php
// Enable full error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apache2/php_errors.log');

// Log script execution
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - build_agnos_raylib.php started\n", FILE_APPEND);

# Constants
define("E", "27182818284590452353602874713526624977572470936999595");  # placeholder for username, includes "openpilot" repo name
define("PI", "314159265358979323846264338327950288419");  # placeholder for loading msg
define("GOLDEN", "161803398874989484820458683436563811772030917980576286213544862270526046281890244970720720418939113748475408807538689175212663386222353693179318006076672635443338908659593958290563832266131992829026788067520876689250171169620703222104321626954862629631361");  # placeholder for branch

# Replaces placeholder with input + any needed NULs, plus does length checking
function fill_in_arg($placeholder, $replace_with, $binary, $padding, $arg_type) {
    $placeholder_len = mb_strlen($placeholder);
    if ($placeholder_len - strlen($replace_with) < 0) { echo "Error: Invalid " . $arg_type . " length!"; exit; }

    $replace_with .= str_repeat($padding, $placeholder_len - strlen($replace_with));
    return str_replace($placeholder, $replace_with, $binary);
}


# Load raylib installer binary with error handling
$binary_path = getcwd() . "/installer_openpilot_agnos_raylib";
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Attempting to load raylib binary from: " . $binary_path . "\n", FILE_APPEND);

if (!file_exists($binary_path)) {
    file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - ERROR: Raylib binary file not found at: " . $binary_path . "\n", FILE_APPEND);
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Raylib installer binary not found. Please contact the administrator.";
    exit;
}

$installer_binary = file_get_contents($binary_path);  # load the unmodified installer
if ($installer_binary === false) {
    file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - ERROR: Failed to read raylib binary file: " . $binary_path . "\n", FILE_APPEND);
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Failed to read raylib installer binary. Please contact the administrator.";
    exit;
}

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Successfully loaded raylib binary file: " . $binary_path . " (size: " . strlen($installer_binary) . " bytes)\n", FILE_APPEND);

$username = $_GET["username"];
$repo = isset($_GET["repo"]) ? $_GET["repo"] : "openpilot";  # Default to openpilot for backward compatibility
$branch = $_GET["branch"];
$loading_msg = $_GET["loading_msg"];

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Parameters: username=$username, repo=$repo, branch=$branch, loading_msg=$loading_msg\n", FILE_APPEND);

if ($username == "") exit;  # discount assertions
if ($loading_msg == "") exit;


# Handle username replacement:
$installer_binary = fill_in_arg(E, $username . "/" . $repo . ".git", $installer_binary, "\0", "username");
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Using repo: $repo in git URL\n", FILE_APPEND);

# Handle branch replacement (3 occurrences):
$installer_binary = fill_in_arg(GOLDEN, $branch, $installer_binary, "\0", "branch");

# Handle loading message replacement:
$installer_binary = fill_in_arg(PI, $loading_msg, $installer_binary, " ", "loading message");  // Raylib displays null characters properly


# Now download
header("Content-Type: application/octet-stream");
header("Content-Length: " . strlen($installer_binary));  # we want actual bytes
header("Content-Disposition: attachment; filename=installer_openpilot_raylib");
echo $installer_binary;  # downloads without saving to a file
exit;
?>