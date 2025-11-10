<?php
// Enable full error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apache2/php_errors.log');

// Log script execution
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Script started\n", FILE_APPEND);

# Constants
define("USER_AGENT", $_SERVER['HTTP_USER_AGENT']);
define("IS_NEOS", str_contains(USER_AGENT, "NEOSSetup"));
define("IS_AGNOS", str_contains(USER_AGENT, "AGNOSSetup"));
define("IS_WGET", str_contains(USER_AGENT, "Wget"));
# Use release2 if NEOS, else release3 (careful! wget assumes comma three)
define("DEFAULT_STOCK_BRANCH", IS_NEOS ? "release2" : "release3");

define("WEBSITE_URL", "https://smiskol.com");
define("BASE_DIR", "/" . basename(__DIR__));

function logData() {
    global $url;
    global $username;
    global $repo_name;
    global $branch;
    date_default_timezone_set('America/Chicago');

    $data = array("IP" => $_SERVER['REMOTE_ADDR'], "url" => $url, "username" => $username, "repo" => $repo_name, "branch" => $branch, "is_neos" => IS_NEOS, "is_agnos" => IS_AGNOS, "is_wget" => IS_WGET, "user_agent" => USER_AGENT, "date" => date("Y-m-d_H:i:s",time()));
    $data = json_encode($data);

    $fp = fopen("log.txt", "a");
    fwrite($fp, $data."\n");
    fclose($fp);
}

// Log request details
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Request: " . print_r($_SERVER, true) . "\n", FILE_APPEND);
file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - GET: " . print_r($_GET, true) . "\n", FILE_APPEND);

$url = "/";
if (array_key_exists("url", $_GET)) {
    $url = $_GET["url"];
}

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - URL: " . $url . "\n", FILE_APPEND);

// Safer URL parsing with error handling
$parts = explode("/", $url);

// Handle both old and new URL formats
if (count($parts) >= 3) {
    // New format: username/repo/branch[/loading_msg]
    $username = isset($parts[0]) ? $parts[0] : "";
    $repo_name = isset($parts[1]) ? $parts[1] : "openpilot";
    $branch = isset($parts[2]) ? $parts[2] : "";
    $loading_msg = isset($parts[3]) ? $parts[3] : "";
} else {
    // Old format: username/branch[/loading_msg]
    $username = isset($parts[0]) ? $parts[0] : "";
    $repo_name = "openpilot"; // Default
    $branch = isset($parts[1]) ? $parts[1] : "";
    $loading_msg = isset($parts[2]) ? $parts[2] : "";
}

file_put_contents('/var/log/apache2/debug.log', date('Y-m-d H:i:s') . " - Parsed: username=$username, repo=$repo_name, branch=$branch, loading_msg=$loading_msg\n", FILE_APPEND);

$username = substr(strtolower($username), 0, 39);  # max GH username length
$repo_name = substr(trim($repo_name), 0, 100);  # reasonable limit for repo name
$repo_name = $repo_name == "_" ? "openpilot" : $repo_name;
$branch = substr(trim($branch), 0, 255);  # max GH branch
$branch = $branch == "_" ? "" : $branch;
$loading_msg = substr(trim($loading_msg), 0, 39);
$supplied_loading_msg = $loading_msg != "";  # to print secret message

class Alias {
    public $name, $default_branch, $aliases, $repo, $loading_msg;
    public function __construct($name, $default_branch, $aliases, $repo, $loading_msg) {
        $this->name = $name;  # actual GitHub username
        $this->default_branch = $default_branch;
        $this->aliases = $aliases;
        $this->repo = $repo;  # name of actual repo
        $this->loading_msg = $loading_msg;
    }
}

# Handle aliases
$aliases = [new Alias("dragonpilot-community", "release3", ["dragonpilot", "dp"], "", "dragonpilot"),
            new Alias("commaai", DEFAULT_STOCK_BRANCH, ["stock", "commaai"], "", "openpilot"),
            new Alias("sshane", "SA-master", ["shane", "smiskol", "sa", "sshane"], "", "Stock Additions"),
	    new Alias("sunnyhaibin", "prod-c3", ["sunnypilot", "sp", "sunnyhaibin"], "", "sunnypilot")];
foreach ($aliases as $al) {
    if (in_array($username, $al->aliases)) {
        $username = $al->name;
        if ($branch == "") $branch = $al->default_branch;  # if unspecified, use default
        if ($loading_msg == "") $loading_msg = $al->loading_msg;
        if ($al->repo != "" && $repo_name == "openpilot") $repo_name = $al->repo;  # Only override if using default repo
        break;
    }
}
if ($loading_msg == "") {  # if not an alias with custom msg and not specified use username
    $loading_msg = $username;
} else {  # make sure we encode spaces, neos setup doesn't like spaces (branch and username shouldn't have spaces)
	$loading_msg = str_replace(" ", "%20", $loading_msg);
}

logData();

$build_script = IS_NEOS ? "/build_neos.php" : "/build_agnos.php";
if (IS_NEOS or IS_AGNOS or IS_WGET) {  # if NEOS or wget serve file immediately. commaai/stock if no username provided
    if ($username == "") {
        $username = "commaai";
        $repo_name = "openpilot";
        $branch = DEFAULT_STOCK_BRANCH;
        $loading_msg = "openpilot";
    }
    header("Location: " . BASE_DIR . $build_script . "?username=" . $username . "&repo=" . $repo_name . "&branch=" . $branch . "&loading_msg=" . $loading_msg);
    return;
}

# Draws visual elements for website
echo '<head>
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
<style>
body {background-image: linear-gradient(#F9DEC9, #99B2DD); font-family: "Roboto", sans-serif; color: #30323D; text-align: center;}
span { color: #6369D1; }
a { text-decoration: none; color: #6369D1;}
button[name="download_neos"] {background-color: #cb99c5; border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 4px 0px #AD83A8; display: inline-block; color: white;top: 1px; outline: 0px transparent !important;}
button:active[name="download_neos"] {border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 2px 2px #BA8CB5; background-color: #BA8CB5; display: inline-block; top: 1px, outline: 0px transparent !important;}

button[name="download_agnos"] {background-color: #ace6df; border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 4px 0px #80c2ba; display: inline-block; color: #30323D;top: 1px; outline: 0px transparent !important;}
button:active[name="download_agnos"] {border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 2px 2px #89c7c7; background-color: #89c7c7; display: inline-block; top: 1px, outline: 0px transparent !important;}

button[name="download_agnos_raylib"] {background-color: #ffb347; border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 4px 0px #e6a040; display: inline-block; color: #30323D;top: 1px; outline: 0px transparent !important;}
button:active[name="download_agnos_raylib"] {border-radius: 4px; border: 5px; padding: 10px 12px; box-shadow:0px 2px 2px #e6a040; background-color: #e6a040; display: inline-block; top: 1px, outline: 0px transparent !important;}
</style>
<title>fork installer generator</title>
<link rel="icon" type="image/x-icon" href="' . BASE_DIR . '/favicon.ico">
</head>';

echo '</br></br><a href="' . BASE_DIR . '"><h1 style="color: #30323D;">🍴 openpilot fork installer generator-inator 🍴</h1></a>';
echo '<h3 style="position: absolute; bottom: 0; left: 0; width: 100%; text-align: center;"><a href="https://github.com/sshane/openpilot-installer-generator" style="color: 30323D;">💾 Installer Generator GitHub Repo</a></h3>';

if ($username == "") {
    echo '<h3 style="color: #30323D;">🎉 now supports comma three! 🎉<h3>';
    echo "</br><h2>Enter this URL on your device during setup with the format:</h2>";
    echo "<h2><a href='" . BASE_DIR . "/sshane/openpilot/SA-master'><span>" . WEBSITE_URL . BASE_DIR . "/username/repo/branch</span></a></h2>";
    echo "</br><h3>Or complete the request on your desktop to download a custom installer.</h3>";
    exit;
}
echo '<h3>Given fork username: <a href="https://github.com/' . $username . '/' . $repo_name . '">' . $username . '</a></h3>';
echo '<h3>Repository: <a href="https://github.com/' . $username . '/' . $repo_name . '">' . $repo_name . '</a></h3>';



if ($branch != "") {
    echo '<h3>Given branch: <a href="https://github.com/'.$username.'/' . $repo_name . '/tree/'.$branch.'">' . $branch . '</a></h3>';
} else {
    echo '<h3>❗ No branch supplied, git will use default GitHub branch ❗</h3>';
}

if ($loading_msg != "" and $supplied_loading_msg) {
    echo '<h3>You\'ve discovered a hidden secret!</br>When using this binary, this custom message will be shown: <span>Installing ' . $loading_msg . '</span></h3>';
}

echo '<html>
    <body>
        <form method="post">
        <button class="button" name="download_neos">Download Android Installer Binary</button>
        <button class="button" name="download_agnos">Download AGNOS Installer Binary (Qt - Legacy)</button>
        <button class="button" name="download_agnos_raylib">Download AGNOS Installer Binary (Raylib - Modern)</button>
    </form>
    <h5>Or enter this URL on the setup screen on your device.</h5>
    <h6>💡 Use Raylib version for AGNOS >14.3, Qt version for older AGNOS versions</h6>
    </body>
</html>';

if(array_key_exists('download_neos', $_POST)) {
    header("Location: " . BASE_DIR . "/build_neos.php?username=" . $username . "&repo=" . $repo_name . "&branch=" . $branch . "&loading_msg=" . $loading_msg);
    exit;
}
if(array_key_exists('download_agnos', $_POST)) {
    header("Location: " . BASE_DIR . "/build_agnos.php?username=" . $username . "&repo=" . $repo_name . "&branch=" . $branch . "&loading_msg=" . $loading_msg);
    exit;
}
if(array_key_exists('download_agnos_raylib', $_POST)) {
    header("Location: " . BASE_DIR . "/build_agnos_raylib.php?username=" . $username . "&repo=" . $repo_name . "&branch=" . $branch . "&loading_msg=" . $loading_msg);
    exit;
}
?>
