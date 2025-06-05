<?php
session_start();
include('/var/cbinfo_connect.php');

// Utility function for settings
function get_setting($key, $dbc) {
    $stmt = mysqli_prepare($dbc, "SELECT setting_value FROM app_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $val);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $val;
}

$auth_method = get_setting('auth_method', $dbc);
if (!$auth_method) $auth_method = 'local'; // fallback for blank DB
$login_error = '';
$remember_checked = false;

// Handle local login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['local_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_checked = !empty($_POST['remember_me']);

    $stmt = $dbc->prepare("SELECT id, email, name, role, password, active FROM CBUsers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_email, $name, $role, $db_password, $active);
        $stmt->fetch();
        if (!$active) {
            $login_error = "This account is disabled. Contact your administrator.";
        } elseif (!empty($db_password) && password_verify($password, $db_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $db_email;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;
            // --- "Remember Me" cookie logic ---
            if ($remember_checked) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30 days
                setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, "/", "", true, true);
                $update = $dbc->prepare("UPDATE CBUsers SET remember_token=?, remember_expiry=? WHERE id=?");
                $update->bind_param("ssi", $token, $expiry, $id);
                $update->execute();
            }
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Incorrect password.";
        }
    } else {
        $login_error = "No active user found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #181A20;
            color: #EDEDED;
        }
        .login-card {
            max-width: 420px;
            margin: 60px auto 0 auto;
            background: #23272F;
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 18px;
            box-shadow: 0 4px 22px #10121a33;
        }
        .brand {
            font-size: 2rem;
            color: #ffcb4b;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .tab-btn {
            background: none !important;
            color: #ffcb4b !important;
            border: none !important;
            font-weight: bold;
            padding: 0.5rem 1.2rem;
            transition: color 0.15s;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active, .tab-btn:focus {
            color: #23272F !important;
            background: #ffcb4b !important;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #ffcb4b;
        }
        .form-label {
            color: #ffcb4b;
            font-weight: 600;
            font-size: 1em;
        }
        .btn-gold {
            background: #ffcb4b;
            color: #181A20;
            font-weight: bold;
        }
        .btn-gold:hover, .btn-gold:focus {
            background: #e6b800;
            color: #181A20;
        }
        .btn-google {
            background: #dd4b39;
            color: #fff;
            font-weight: 600;
        }
        .btn-google:hover {
            background: #c23321;
            color: #fff;
        }
        .form-check-input:checked {
            background-color: #ffcb4b;
            border-color: #ffcb4b;
        }
        .form-check-label {
            color: #ffcb4b;
            font-weight: 500;
        }
        .alert-danger {
            background: #372222;
            color: #ffa7a7;
            border: none;
        }
    </style>
    <script>
        function showTab(tab) {
            document.getElementById('localTab').style.display = tab === 'local' ? 'block' : 'none';
            document.getElementById('googleTab').style.display = tab === 'google' ? 'block' : 'none';
            document.getElementById('localTabBtn').classList.toggle('active', tab === 'local');
            document.getElementById('googleTabBtn').classList.toggle('active', tab === 'google');
        }
        function setRememberForGoogle() {
            var checked = document.getElementById('remember_me').checked;
            var googleBtn = document.getElementById('googleLoginBtn');
            var url = "google_auth_start.php";
            if (checked) {
                url += "?remember=1";
            }
            googleBtn.href = url;
        }
        // Ensure Google link is updated if "Remember Me" is toggled
        document.addEventListener("DOMContentLoaded", function() {
            var rememberBox = document.getElementById('remember_me');
            if (rememberBox) {
                rememberBox.addEventListener('change', setRememberForGoogle);
            }
            setRememberForGoogle();
        });
    </script>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <span class="brand"><i class="fa fa-laptop me-2"></i>CATS</span>
        <div style="font-size:1.12em;color:#ffe38a;margin-top:.6em;">Chromebook Asset Tracking System</div>
    </div>
    <?php if ($login_error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>

    <?php if ($auth_method == 'both'): ?>
        <div class="mb-3 d-flex justify-content-center">
            <button class="tab-btn active" id="localTabBtn" type="button" onclick="showTab('local')">Local Login</button>
            <button class="tab-btn" id="googleTabBtn" type="button" onclick="showTab('google')">Google Login</button>
        </div>
        <!-- Unified Remember Me Checkbox -->
        <div class="mb-3 form-check text-center">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1" <?= $remember_checked ? 'checked' : '' ?>>
            <label class="form-check-label" for="remember_me">Remember Me</label>
        </div>
        <div id="localTab" class="tab-pane active">
            <form method="post" autocomplete="off">
                <input type="hidden" name="local_login" value="1">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input name="email" type="email" required class="form-control" id="email" autocomplete="username" autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input name="password" type="password" required class="form-control" id="password" autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-gold w-100">Sign in</button>
            </form>
        </div>
        <div id="googleTab" class="tab-pane" style="display:none;">
            <div class="d-grid">
                <a id="googleLoginBtn" href="google_auth_start.php" class="btn btn-google w-100 mb-2">
                    <i class="fab fa-google me-2"></i>Sign in with Google
                </a>
            </div>
        </div>
    <?php elseif ($auth_method == 'google'): ?>
        <div class="mb-3 form-check text-center">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1">
            <label class="form-check-label" for="remember_me">Remember Me</label>
        </div>
        <div class="d-grid">
            <a id="googleLoginBtn" href="google_auth_start.php" class="btn btn-google w-100 mb-2">
                <i class="fab fa-google me-2"></i>Sign in with Google
            </a>
        </div>
        <script>
            document.getElementById('remember_me').addEventListener('change', setRememberForGoogle);
            setRememberForGoogle();
        </script>
    <?php else: // Local login only ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="local_login" value="1">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input name="email" type="email" required class="form-control" id="email" autocomplete="username" autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input name="password" type="password" required class="form-control" id="password" autocomplete="current-password">
            </div>
            <div class="mb-3 form-check text-center">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1" <?= $remember_checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember_me">Remember Me</label>
            </div>
            <button type="submit" class="btn btn-gold w-100">Sign in</button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-4" style="font-size:0.9em;">
        <span style="color:#ffcb4b;">Need help?</span> Contact your system administrator.
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
