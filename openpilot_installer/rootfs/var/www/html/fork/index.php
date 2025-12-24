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

debug_log("index.php started");

# Constants
define("USER_AGENT", isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
define("IS_NEOS", strpos(USER_AGENT, "NEOSSetup") !== false);
define("IS_AGNOS", strpos(USER_AGENT, "AGNOSSetup") !== false);
define("IS_WGET", strpos(USER_AGENT, "Wget") !== false);
define("IS_CURL", strpos(USER_AGENT, "curl") !== false);

# Use release2 if NEOS, else release3 (careful! wget/curl assumes comma three)
define("DEFAULT_STOCK_BRANCH", IS_NEOS ? "release2" : "release3");

define("BASE_DIR", "/" . basename(__DIR__));

function logData() {
    global $url, $username, $repo_name, $branch;
    date_default_timezone_set('UTC');

    $data = array(
        "IP" => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        "url" => $url,
        "username" => $username,
        "repo" => $repo_name,
        "branch" => $branch,
        "is_neos" => IS_NEOS,
        "is_agnos" => IS_AGNOS,
        "is_wget" => IS_WGET,
        "user_agent" => USER_AGENT,
        "date" => date("Y-m-d_H:i:s", time())
    );
    $data = json_encode($data);

    $fp = fopen(__DIR__ . "/log.txt", "a");
    if ($fp) {
        fwrite($fp, $data . "\n");
        fclose($fp);
    }
}

debug_log("Request: " . json_encode($_SERVER['REQUEST_URI'] ?? ''));
debug_log("User-Agent: " . USER_AGENT);

$url = "/";
if (array_key_exists("url", $_GET)) {
    $url = $_GET["url"];
}

debug_log("URL parameter: " . $url);

// Parse URL - supports multiple formats:
// username/branch
// username/repo/branch
// username/repo/branch/loading_msg
$parts = array_filter(explode("/", trim($url, "/")));
$parts = array_values($parts);  // Re-index array

$username = "";
$repo_name = "openpilot";
$branch = "";
$loading_msg = "";

if (count($parts) >= 3) {
    // Format: username/repo/branch[/loading_msg]
    $username = $parts[0];
    $repo_name = $parts[1];
    $branch = $parts[2];
    $loading_msg = isset($parts[3]) ? $parts[3] : "";
} elseif (count($parts) >= 2) {
    // Format: username/branch[/loading_msg]
    $username = $parts[0];
    $branch = $parts[1];
    $loading_msg = isset($parts[2]) ? $parts[2] : "";
} elseif (count($parts) == 1) {
    // Just username - use default branch
    $username = $parts[0];
}

debug_log("Parsed: username=$username, repo=$repo_name, branch=$branch, loading_msg=$loading_msg");

// Sanitize inputs
$username = substr(strtolower(trim($username)), 0, 39);  // max GH username length
$repo_name = substr(trim($repo_name), 0, 100);
$repo_name = ($repo_name == "_" || $repo_name == "") ? "openpilot" : $repo_name;
$branch = substr(trim($branch), 0, 255);  // max GH branch
$branch = $branch == "_" ? "" : $branch;
$loading_msg = substr(trim($loading_msg), 0, 39);
$supplied_loading_msg = $loading_msg != "";

// Handle aliases
class Alias {
    public $name, $default_branch, $aliases, $repo, $loading_msg;
    public function __construct($name, $default_branch, $aliases, $repo, $loading_msg) {
        $this->name = $name;
        $this->default_branch = $default_branch;
        $this->aliases = $aliases;
        $this->repo = $repo;
        $this->loading_msg = $loading_msg;
    }
}

$aliases = [
    new Alias("dragonpilot-community", "release3", ["dragonpilot", "dp"], "", "dragonpilot"),
    new Alias("commaai", DEFAULT_STOCK_BRANCH, ["stock", "commaai", "comma"], "", "openpilot"),
    new Alias("sshane", "SA-master", ["shane", "smiskol", "sa", "sshane"], "", "Stock Additions"),
    new Alias("sunnyhaibin", "prod-c3", ["sunnypilot", "sp", "sunnyhaibin"], "", "sunnypilot")
];

foreach ($aliases as $al) {
    if (in_array($username, $al->aliases)) {
        $username = $al->name;
        if ($branch == "") $branch = $al->default_branch;
        if ($loading_msg == "") $loading_msg = $al->loading_msg;
        if ($al->repo != "" && $repo_name == "openpilot") $repo_name = $al->repo;
        break;
    }
}

if ($loading_msg == "") {
    $loading_msg = $username;
} else {
    $loading_msg = str_replace(" ", "%20", $loading_msg);
}

logData();

// Determine which build script to use
// For AGNOS 14.3+, use raylib version
// For older AGNOS or NEOS, use legacy versions
$build_script = "/build_agnos_raylib.php";  // Default to raylib for modern devices
if (IS_NEOS) {
    $build_script = "/build_neos.php";
}

// If device is requesting (NEOS, AGNOS, wget, or curl), serve the installer directly
if (IS_NEOS || IS_AGNOS || IS_WGET || IS_CURL) {
    if ($username == "") {
        // Default to stock openpilot
        $username = "commaai";
        $repo_name = "openpilot";
        $branch = DEFAULT_STOCK_BRANCH;
        $loading_msg = "openpilot";
    }

    $redirect_url = BASE_DIR . $build_script . "?username=" . urlencode($username) .
                    "&repo=" . urlencode($repo_name) .
                    "&branch=" . urlencode($branch) .
                    "&loading_msg=" . urlencode($loading_msg);

    debug_log("Redirecting device to: $redirect_url");
    header("Location: " . $redirect_url);
    exit;
}

// Web interface for browsers
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>openpilot Fork Installer Generator</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_DIR; ?>/favicon.ico">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #e0e0e0;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #00d4ff;
            font-size: 2em;
            margin-bottom: 10px;
        }
        h2, h3 {
            text-align: center;
            color: #a0a0a0;
        }
        .info-box {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .url-example {
            background: rgba(0,212,255,0.1);
            border: 1px solid #00d4ff;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            font-size: 1.1em;
            text-align: center;
            margin: 15px 0;
            word-break: break-all;
        }
        a { color: #00d4ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        button, .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #00d4ff;
            color: #1a1a2e;
        }
        .btn-primary:hover {
            background: #00a8cc;
        }
        .btn-secondary {
            background: #4a4a6a;
            color: #e0e0e0;
        }
        .btn-secondary:hover {
            background: #5a5a7a;
        }
        .fork-info {
            background: rgba(0,212,255,0.05);
            border-left: 3px solid #00d4ff;
            padding: 15px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        code {
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍴 openpilot Fork Installer Generator</h1>

        <?php if ($username == ""): ?>
            <div class="info-box">
                <h3>How to Use</h3>
                <p>Enter this URL on your comma device during setup:</p>
                <div class="url-example">
                    <strong>your-hassio-ip:8099/fork/username/branch</strong>
                </div>
                <p>Or with a custom repository name:</p>
                <div class="url-example">
                    <strong>your-hassio-ip:8099/fork/username/repo/branch</strong>
                </div>
            </div>

            <div class="info-box">
                <h3>Examples</h3>
                <ul>
                    <li><a href="<?php echo BASE_DIR; ?>/commaai/release3">commaai/release3</a> - Stock openpilot</li>
                    <li><a href="<?php echo BASE_DIR; ?>/sunnyhaibin/prod-c3">sunnyhaibin/prod-c3</a> - sunnypilot</li>
                    <li><a href="<?php echo BASE_DIR; ?>/dragonpilot-community/release3">dragonpilot-community/release3</a> - dragonpilot</li>
                </ul>
            </div>

            <div class="info-box">
                <h3>Supported Aliases</h3>
                <ul>
                    <li><code>stock</code> or <code>comma</code> → commaai/openpilot</li>
                    <li><code>sp</code> or <code>sunnypilot</code> → sunnyhaibin</li>
                    <li><code>dp</code> or <code>dragonpilot</code> → dragonpilot-community</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="fork-info">
                <h3>Fork Details</h3>
                <p><strong>Username:</strong> <a href="https://github.com/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($repo_name); ?>" target="_blank"><?php echo htmlspecialchars($username); ?></a></p>
                <p><strong>Repository:</strong> <a href="https://github.com/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($repo_name); ?>" target="_blank"><?php echo htmlspecialchars($repo_name); ?></a></p>
                <?php if ($branch != ""): ?>
                    <p><strong>Branch:</strong> <a href="https://github.com/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($repo_name); ?>/tree/<?php echo htmlspecialchars($branch); ?>" target="_blank"><?php echo htmlspecialchars($branch); ?></a></p>
                <?php else: ?>
                    <p><strong>Branch:</strong> <em>Default (will use repository's default branch)</em></p>
                <?php endif; ?>
                <?php if ($supplied_loading_msg): ?>
                    <p><strong>Custom Message:</strong> Installing <?php echo htmlspecialchars(urldecode($loading_msg)); ?></p>
                <?php endif; ?>
            </div>

            <div class="info-box">
                <h3>Download Installer</h3>
                <form method="post" class="button-group">
                    <button type="submit" name="download_raylib" class="btn btn-primary">
                        📱 Download AGNOS Installer (Raylib)
                    </button>
                    <button type="submit" name="download_agnos" class="btn btn-secondary">
                        📱 Download AGNOS Installer (Qt Legacy)
                    </button>
                    <?php if (IS_NEOS): ?>
                    <button type="submit" name="download_neos" class="btn btn-secondary">
                        📱 Download NEOS Installer
                    </button>
                    <?php endif; ?>
                </form>
                <p style="text-align: center; font-size: 0.9em; color: #888;">
                    💡 Use <strong>Raylib</strong> version for AGNOS 14.3+<br>
                    Use <strong>Qt Legacy</strong> for older AGNOS versions
                </p>
            </div>

            <div class="info-box">
                <h3>Or Enter This URL on Your Device</h3>
                <div class="url-example">
                    <?php
                    $device_url = $_SERVER['HTTP_HOST'] . BASE_DIR . "/" . $username;
                    if ($repo_name != "openpilot") $device_url .= "/" . $repo_name;
                    $device_url .= "/" . $branch;
                    echo htmlspecialchars($device_url);
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>
                <a href="https://github.com/sshane/openpilot-installer-generator" target="_blank">
                    💾 Installer Generator on GitHub
                </a>
            </p>
            <p style="font-size: 0.8em; color: #666;">
                Self-hosted openpilot installer generator for Home Assistant
            </p>
        </div>
    </div>
</body>
</html>
<?php
// Handle form submissions
if (isset($_POST['download_raylib'])) {
    $redirect = BASE_DIR . "/build_agnos_raylib.php?username=" . urlencode($username) .
                "&repo=" . urlencode($repo_name) .
                "&branch=" . urlencode($branch) .
                "&loading_msg=" . urlencode($loading_msg);
    header("Location: " . $redirect);
    exit;
}
if (isset($_POST['download_agnos'])) {
    $redirect = BASE_DIR . "/build_agnos.php?username=" . urlencode($username) .
                "&repo=" . urlencode($repo_name) .
                "&branch=" . urlencode($branch) .
                "&loading_msg=" . urlencode($loading_msg);
    header("Location: " . $redirect);
    exit;
}
if (isset($_POST['download_neos'])) {
    $redirect = BASE_DIR . "/build_neos.php?username=" . urlencode($username) .
                "&repo=" . urlencode($repo_name) .
                "&branch=" . urlencode($branch) .
                "&loading_msg=" . urlencode($loading_msg);
    header("Location: " . $redirect);
    exit;
}
?>
