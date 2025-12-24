<?php
// Enable full error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apache2/php_errors.log');

// Log script execution
$log_file = '/var/log/apache2/debug.log';
function debug_log($msg) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

debug_log("build_agnos_raylib.php started");

// The official openpilot installer uses these patterns:
// GIT_URL: "https://github.com/commaai/openpilot.git" followed by "?" and spaces
// BRANCH: defined at compile time with "?" and spaces after

// Placeholders in the official installer binary
// The URL pattern: https://github.com/commaai/openpilot.git?<spaces>
define("ORIGINAL_GIT_URL", "https://github.com/commaai/openpilot.git");
define("GIT_URL_PADDING_CHAR", " ");  // spaces for padding after ?
define("GIT_URL_TOTAL_LENGTH", 105);  // Total length including padding

// For custom installers built with our source, use these placeholders:
define("CUSTOM_GIT_URL_PLACEHOLDER", "27182818284590452353602874713526624977572470936999595");  // 53 chars
define("CUSTOM_BRANCH_PLACEHOLDER", "161803398874989484820458683436563811772030917980576286213544862270526046281890244970720720418939113748475408807538689175212663386222353693179318006076672635443338908659593958290563832266131992829026788067520876689250171169620703222104321626954862629631361");  // 255 chars
define("CUSTOM_LOADING_MSG_PLACEHOLDER", "314159265358979323846264338327950288419");  // 39 chars

/**
 * Replace a placeholder in the binary with a new value, padding with the specified character
 */
function patch_binary($binary, $placeholder, $new_value, $padding_char = "\0") {
    $placeholder_len = strlen($placeholder);
    $new_value_len = strlen($new_value);

    if ($new_value_len > $placeholder_len) {
        debug_log("ERROR: New value too long. Max: $placeholder_len, Got: $new_value_len");
        return false;
    }

    // Pad the new value to match placeholder length
    $padded_value = $new_value . str_repeat($padding_char, $placeholder_len - $new_value_len);

    // Find and replace
    $pos = strpos($binary, $placeholder);
    if ($pos === false) {
        debug_log("WARNING: Placeholder not found in binary: " . substr($placeholder, 0, 30) . "...");
        return $binary;  // Return unchanged if not found
    }

    debug_log("Found placeholder at position $pos, replacing with: $new_value");
    return substr_replace($binary, $padded_value, $pos, $placeholder_len);
}

/**
 * Patch the official openpilot installer binary
 * The official binary has the URL and branch embedded with ? delimiter and space padding
 */
function patch_official_installer($binary, $username, $repo, $branch) {
    // The official installer has:
    // "https://github.com/commaai/openpilot.git?                                                                "
    // We need to replace "commaai/openpilot.git?<spaces>" with "username/repo.git?<spaces>"

    $original_pattern = "commaai/openpilot.git";
    $new_pattern = $username . "/" . $repo . ".git";

    // Ensure the new pattern isn't longer than original + available padding
    $max_length = strlen($original_pattern) + 64;  // 64 spaces of padding available
    if (strlen($new_pattern) > $max_length) {
        debug_log("ERROR: Username/repo too long. Max combined length: $max_length");
        return false;
    }

    // Find the original pattern
    $pos = strpos($binary, $original_pattern);
    if ($pos !== false) {
        debug_log("Found official installer pattern at position $pos");

        // Replace with new pattern, keeping the ? delimiter
        // The format is: username/repo.git?<padding>
        $replacement = $new_pattern;

        // Find how much space we have (look for the ? and count spaces after)
        $question_pos = strpos($binary, $original_pattern . "?");
        if ($question_pos !== false) {
            // Count spaces after the ?
            $after_question = $question_pos + strlen($original_pattern) + 1;
            $space_count = 0;
            while ($after_question + $space_count < strlen($binary) &&
                   $binary[$after_question + $space_count] === ' ') {
                $space_count++;
            }

            $total_available = strlen($original_pattern) + 1 + $space_count;  // pattern + ? + spaces
            $new_with_padding = $new_pattern . "?" . str_repeat(" ", $total_available - strlen($new_pattern) - 1);

            $binary = substr_replace($binary, $new_with_padding, $question_pos, $total_available);
            debug_log("Patched URL successfully");
        }
    }

    // Now patch the branch - this is trickier as it's compiled in
    // The branch is typically "release3" or similar, followed by ? and spaces
    // We need to find the branch pattern in the binary

    // Common branch patterns to look for
    $branch_patterns = ["release3?", "release3-staging?", "nightly?", "nightly-dev?"];

    foreach ($branch_patterns as $pattern) {
        $pos = strpos($binary, $pattern);
        if ($pos !== false) {
            debug_log("Found branch pattern '$pattern' at position $pos");

            // Count spaces after
            $after_pattern = $pos + strlen($pattern);
            $space_count = 0;
            while ($after_pattern + $space_count < strlen($binary) &&
                   $binary[$after_pattern + $space_count] === ' ') {
                $space_count++;
            }

            $total_available = strlen($pattern) - 1 + $space_count;  // pattern without ? + spaces
            if (strlen($branch) <= $total_available) {
                $new_branch = $branch . "?" . str_repeat(" ", $total_available - strlen($branch));
                $binary = substr_replace($binary, $new_branch, $pos, strlen($pattern) + $space_count);
                debug_log("Patched branch successfully");
            } else {
                debug_log("WARNING: Branch name too long for available space");
            }
            break;
        }
    }

    return $binary;
}

/**
 * Patch a custom-built installer with our placeholders
 */
function patch_custom_installer($binary, $username, $repo, $branch, $loading_msg) {
    // Patch git URL (just the username/repo.git part)
    $git_path = $username . "/" . $repo . ".git";
    $binary = patch_binary($binary, CUSTOM_GIT_URL_PLACEHOLDER, $git_path, "\0");
    if ($binary === false) return false;

    // Patch branch
    $binary = patch_binary($binary, CUSTOM_BRANCH_PLACEHOLDER, $branch, "\0");
    if ($binary === false) return false;

    // Patch loading message (use space padding for display)
    $binary = patch_binary($binary, CUSTOM_LOADING_MSG_PLACEHOLDER, $loading_msg, " ");
    if ($binary === false) return false;

    return $binary;
}

// Main execution
debug_log("Processing request");

// Get parameters
$username = isset($_GET["username"]) ? trim($_GET["username"]) : "";
$repo = isset($_GET["repo"]) ? trim($_GET["repo"]) : "openpilot";
$branch = isset($_GET["branch"]) ? trim($_GET["branch"]) : "";
$loading_msg = isset($_GET["loading_msg"]) ? trim($_GET["loading_msg"]) : "";

debug_log("Parameters: username=$username, repo=$repo, branch=$branch, loading_msg=$loading_msg");

// Validate required parameters
if ($username === "") {
    debug_log("ERROR: No username provided");
    header("HTTP/1.1 400 Bad Request");
    echo "Error: Username is required";
    exit;
}
if ($branch === "") {
    debug_log("ERROR: No branch provided");
    header("HTTP/1.1 400 Bad Request");
    echo "Error: Branch is required";
    exit;
}
if ($loading_msg === "") {
    $loading_msg = $username;
}

// Try to load the installer binary
// First try the custom raylib installer, then fall back to official
$binary_paths = [
    getcwd() . "/installer_openpilot_agnos_raylib",
    getcwd() . "/installer_openpilot_agnos",
    "/var/www/html/fork/installer_openpilot_agnos_raylib",
    "/var/www/html/fork/installer_openpilot_agnos",
];

$installer_binary = false;
$binary_path = "";
$is_custom = false;

foreach ($binary_paths as $path) {
    if (file_exists($path)) {
        $installer_binary = file_get_contents($path);
        if ($installer_binary !== false) {
            $binary_path = $path;
            // Check if it's our custom installer by looking for our placeholder
            $is_custom = (strpos($installer_binary, CUSTOM_GIT_URL_PLACEHOLDER) !== false);
            debug_log("Loaded binary from: $path (custom: " . ($is_custom ? "yes" : "no") . ", size: " . strlen($installer_binary) . " bytes)");
            break;
        }
    }
}

if ($installer_binary === false) {
    debug_log("ERROR: No installer binary found");
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Installer binary not found. Please ensure the installer binary is present.\n";
    echo "Expected locations:\n";
    foreach ($binary_paths as $path) {
        echo "  - $path\n";
    }
    exit;
}

// Patch the binary
if ($is_custom) {
    debug_log("Using custom installer patching");
    $patched_binary = patch_custom_installer($installer_binary, $username, $repo, $branch, $loading_msg);
} else {
    debug_log("Using official installer patching");
    $patched_binary = patch_official_installer($installer_binary, $username, $repo, $branch);
}

if ($patched_binary === false) {
    debug_log("ERROR: Failed to patch binary");
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Failed to patch installer binary";
    exit;
}

debug_log("Binary patching complete, serving file (" . strlen($patched_binary) . " bytes)");

// Serve the patched binary
header("Content-Type: application/octet-stream");
header("Content-Length: " . strlen($patched_binary));
header("Content-Disposition: attachment; filename=installer_openpilot");
header("Cache-Control: no-cache, no-store, must-revalidate");
echo $patched_binary;
exit;
?>
