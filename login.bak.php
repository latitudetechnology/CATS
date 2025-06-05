<?php
session_start();
if (isset($_SESSION['email'])) {
    // Already logged in, go to dashboard
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Chromebook Asset Tracking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .centered {
            height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .google-btn {
            background: #fff;
            color: #444;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 1.1em;
            padding: 10px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.14);
            display: flex;
            align-items: center;
        }
        .google-btn img {
            width: 24px;
            margin-right: 12px;
        }
    </style>
</head>
<body>
<div class="centered">
    <h2 class="mb-4">Chromebook Asset Tracking</h2>
    <form method="get" action="google_auth_start.php" style="display:flex;flex-direction:column;align-items:center;gap:1em;">
        <button type="submit" class="google-btn">
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo">
            Sign in with Google
        </button>
        <label style="font-size:1.02em;">
            <input type="checkbox" name="remember" value="1" style="margin-right:8px;">
            Remember Me
        </label>
    </form>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger mt-4"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
</div>

</body>
</html>
