<?php
// session_check.php

session_start();

if (!isset($_SESSION['email'])) {
    // Check for remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        include('/var/cbinfo_connect.php');
        $token = $_COOKIE['remember_token'];
        $now = date('Y-m-d H:i:s');
        // Use prepared statement for extra safety
        $stmt = $dbc->prepare("SELECT id, email, name, role, active FROM CBUsers WHERE remember_token=? AND remember_expiry > ? AND active=1 LIMIT 1");
        $stmt->bind_param("ss", $token, $now);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $email, $name, $role, $active);
            $stmt->fetch();
            // Set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $email;
            $_SESSION['name']  = $name;
            $_SESSION['role']  = $role;
            // Refresh expiry so the user stays logged in if active
            $new_expiry = time() + (60 * 60 * 24 * 30);
            $update = $dbc->prepare("UPDATE CBUsers SET remember_expiry=FROM_UNIXTIME(?) WHERE id=?");
            $update->bind_param("ii", $new_expiry, $id);
            $update->execute();
            setcookie('remember_token', $token, $new_expiry, "/", "", true, true);
        }
        $stmt->close();
        // If no user found, just stay not logged in (redirect as usual)
    }
}
// No redirect here—you’ll do that on each page if needed.
?>
