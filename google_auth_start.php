<?php
session_start();
require_once 'vendor/autoload.php'; // Adjust path as needed

include('/var/cbinfo_connect.php'); // Make sure $dbc is your connection variable

function get_setting($key, $dbc) {
    $stmt = $dbc->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($val);
    $stmt->fetch();
    $stmt->close();
    return $val;
}

$google_client_id = get_setting('google_client_id', $dbc);
$google_client_secret = get_setting('google_client_secret', $dbc);

$client = new Google_Client();
$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri('https://data.marcelineschools.org/google_auth_callback.php');
$client->addScope('email');
$client->addScope('profile');

// Restrict login to marcelineschools.org accounts in the UI
$client->setHostedDomain('marcelineschools.org');

// Handle Remember Me (pass through via session to callback)
if (isset($_GET['remember']) && $_GET['remember'] == '1') {
    $_SESSION['remember_me'] = 1;
} else {
    unset($_SESSION['remember_me']);
}

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;
?>
