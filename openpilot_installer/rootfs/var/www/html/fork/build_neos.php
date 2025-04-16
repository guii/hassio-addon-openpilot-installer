<?php
// Enable full error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apache2/php_errors.log');

// Log script execution
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - build_neos.php started\n", FILE_APPEND);

# Constants
define("E", "271828182845904523536028747135266249775724709369995957496696762772407663035354759457138217852516642742746639193200305992181741359662904357290033429526059563073813232862794349076323382988075319525101901157383418793070215408914993488416750924476146066808226");  # placeholder for username
define("PI", "314159265358979323846264338327950288419716939937510582097494459230781640628620899862803482534211706798214808651328230664709384460955058223172535940812848111745028410270193852110555964462294895493038196442881097566593344612847564823378678316527120190914564");
define("NUM_USERNAME_CHARS", mb_strlen(E));
define("NUM_LOADING_CHARS", mb_strlen(PI));
define("BRANCH_START_STR", "--depth=1 ");

# Load installer binary with error handling
$binary_path = getcwd() . "/installer_openpilot_neos";
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Attempting to load binary from: " . $binary_path . "\n", FILE_APPEND);

if (!file_exists($binary_path)) {
    file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - ERROR: Binary file not found at: " . $binary_path . "\n", FILE_APPEND);
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Installer binary not found. Please contact the administrator.";
    exit;
}

$installer_binary = file_get_contents($binary_path);  # load the unmodified installer
if ($installer_binary === false) {
    file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - ERROR: Failed to read binary file: " . $binary_path . "\n", FILE_APPEND);
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Failed to read installer binary. Please contact the administrator.";
    exit;
}

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Successfully loaded binary file: " . $binary_path . " (size: " . strlen($installer_binary) . " bytes)\n", FILE_APPEND);

$username = $_GET["username"];  # might want to make sure these are coming from index.php and not anyone injecting random values
$repo = isset($_GET["repo"]) ? $_GET["repo"] : "openpilot";  # Default to openpilot for backward compatibility
$branch = $_GET["branch"];
$loading_msg = $_GET["loading_msg"];

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Parameters: username=$username, repo=$repo, branch=$branch, loading_msg=$loading_msg\n", FILE_APPEND);

if ($username == "") exit;  # discount assertions
if ($loading_msg == "") exit;

# Handle username replacement
$installer_binary = str_replace(E, $username, $installer_binary);  # replace placeholder with username

$num_nulls_append = NUM_USERNAME_CHARS - mb_strlen($username);  # number of spaces we need to append to end of string before NUL
$branch_start_idx = strpos($installer_binary, BRANCH_START_STR) + mb_strlen(BRANCH_START_STR);

# Add the repository name to the git clone command
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Adding repo name: $repo at position $branch_start_idx\n", FILE_APPEND);
$installer_binary = substr_replace($installer_binary, $repo . " ", $branch_start_idx, 0);
$branch_start_idx += mb_strlen($repo) + 1;  # Update index after adding repo name

$installer_binary = substr_replace($installer_binary, str_repeat(" ", $num_nulls_append), $branch_start_idx, 0);  # 0 inserts, no replacing

if ($branch != "") {
    # Now add user-supplied branch
    $branch_start_idx = strpos($installer_binary, BRANCH_START_STR) + mb_strlen(BRANCH_START_STR) + mb_strlen($repo) + 1;
    $branch_len = mb_strlen($branch) + 4;  # +4 for " -b "
    file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Adding branch: $branch at position $branch_start_idx\n", FILE_APPEND);
    $installer_binary = substr_replace($installer_binary, " -b " . $branch, $branch_start_idx, $branch_len);
}

# Replace loading msg
$num_nulls_append = NUM_LOADING_CHARS - strlen($loading_msg);  # keep size the same
$installer_binary = str_replace(PI, $loading_msg . str_repeat("\0", $num_nulls_append), $installer_binary);

# Now download
header("Content-Type: application/octet-stream");
header("Content-Length: " . strlen($installer_binary));  # we want actual bytes
header("Content-Disposition: attachment; filename=installer_openpilot");
echo $installer_binary;  # downloads without saving to a file
exit;
?>
