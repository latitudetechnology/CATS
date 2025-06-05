<?php
session_start();
require_once 'vendor/autoload.php'; // Adjust path as needed

include('/var/cbinfo_connect.php');

// --- FUNCTION TO GET GOOGLE SETTINGS FROM DB ---
function get_setting($key, $dbc) {
    $stmt = $dbc->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($val);
    $stmt->fetch();
    $stmt->close();
    return $val;
}

$client_id = get_setting('google_client_id', $dbc);
$client_secret = get_setting('google_client_secret', $dbc);

$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri('https://data.marcelineschools.org/google_auth_callback.php');

if (!isset($_GET['code'])) {
    header('Location: login.php?error=No+code+returned+from+Google');
    exit;
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    header('Location: login.php?error=' . urlencode($token['error_description']));
    exit;
}

$client->setAccessToken($token['access_token']);

// Get user info
$oauth2 = new Google_Service_Oauth2($client);
$userInfo = $oauth2->userinfo->get();

$email = $userInfo->email;
$name = $userInfo->name;

// Only allow @marcelineschools.org users
if (strtolower(substr($email, -strlen('@marcelineschools.org'))) !== '@marcelineschools.org') {
    header('Location: login.php?error=Unauthorized+domain');
    exit;
}

$safe_email = mysqli_real_escape_string($dbc, $email);

// ----------- LOOKUP USER IN DATABASE (no auto-create) -----------
$result = mysqli_query($dbc, "SELECT id, role, name, active FROM CBUsers WHERE email='$safe_email'");
if ($row = mysqli_fetch_assoc($result)) {
    if (!$row['active']) {
        header('Location: login.php?error=Account+disabled');
        exit;
    }
    $role = $row['role'];
    $user_id = $row['id'];
    // Optionally update name if changed
    if ($row['name'] !== $name) {
        $safe_name = mysqli_real_escape_string($dbc, $name);
        mysqli_query($dbc, "UPDATE CBUsers SET name='$safe_name', last_login=NOW() WHERE id=$user_id");
    } else {
        mysqli_query($dbc, "UPDATE CBUsers SET last_login=NOW() WHERE id=$user_id");
    }
} else {
    // Not found; don't allow login
    header('Location: login.php?error=You+are+not+authorized+for+this+system');
    exit;
}

// ----------- SET SESSION VARIABLES -----------
$_SESSION['user_id'] = $user_id;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;

// ----------- "REMEMBER ME" COOKIE SETUP -----------
if (!empty($_SESSION['remember_me'])) {
    $remember_token = bin2hex(random_bytes(32));
    $expires = time() + (60 * 60 * 24 * 30); // 30 days
    // Store token and expiry in DB
    $stmt = $dbc->prepare("UPDATE CBUsers SET remember_token=?, remember_expiry=FROM_UNIXTIME(?) WHERE id=?");
    $stmt->bind_param("sii", $remember_token, $expires, $user_id);
    $stmt->execute();
    $stmt->close();
    // Set cookie for 30 days, HTTP only
    setcookie('remember_token', $remember_token, $expires, "/", "", true, true);
    unset($_SESSION['remember_me']);
}

header('Location: index.php');
exit;
?>
