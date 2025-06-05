<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    echo "<div style='color:yellow'>You are not signed in. <a href='login.php'>Sign in</a></div>";
    exit;
} elseif (($_SESSION['role'] ?? 'user') !== 'admin') {
    echo "<div style='color:orange'>You are not an admin. Your role is: <b>" . htmlspecialchars($_SESSION['role'] ?? 'user') . "</b></div>";
    exit;
}
include('/var/cbinfo_connect.php');

// === Settings functions ===
function get_setting($key, $dbc) {
    $stmt = mysqli_prepare($dbc, "SELECT setting_value FROM app_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $val);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $val;
}
function set_setting($key, $value, $dbc) {
    $stmt = mysqli_prepare($dbc, "REPLACE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $key, $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ----------- School Name Setting -----------
$school_name_msg = '';
if (isset($_POST['update_school_name'])) {
    $new_school_name = trim($_POST['school_name'] ?? '');
    set_setting('school_name', $new_school_name, $dbc);
    $school_name_msg = "<div class='alert alert-success mt-2'>School name updated.</div>";
}
$current_school_name = get_setting('school_name', $dbc) ?: "";

// ----------- Student Photo Settings -----------
$student_photo_enabled = get_setting('student_photo_enabled', $dbc) === '1';
$student_photo_msg = '';
if (isset($_POST['update_photo_settings'])) {
    $student_photo_enabled = isset($_POST['student_photo_enabled']) ? 1 : 0;
    set_setting('student_photo_enabled', $student_photo_enabled, $dbc);
    $student_photo_msg = "<div class='alert alert-success mt-2'>Student photo setting updated.</div>";
}

// --- Handle Bulk Upload (zip) ---
if (isset($_POST['upload_zip']) && isset($_FILES['photo_zip']) && $_FILES['photo_zip']['error'] == 0) {
    $zipFile = $_FILES['photo_zip']['tmp_name'];
    $photosDir = __DIR__ . '/student_photos/';
    if (!file_exists($photosDir)) mkdir($photosDir, 0755, true);
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === TRUE) {
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (preg_match('/^(\d+)\.jpg$/i', basename($entry))) {
                $targetPath = $photosDir . basename($entry);
                file_put_contents($targetPath, $zip->getFromIndex($i));
                $count++;
            }
        }
        $zip->close();
        $student_photo_msg .= "<div class='alert alert-success mt-2'>$count student photo(s) uploaded from ZIP.</div>";
    } else {
        $student_photo_msg .= "<div class='alert alert-danger mt-2'>Failed to extract zip file.</div>";
    }
}

// ----------- Authentication settings -----------
$auth_update_msg = null;
if (isset($_POST['update_auth_method'])) {
    $am = $_POST['auth_method'] ?? 'local';
    set_setting('auth_method', $am, $dbc);
    if ($am == 'google' || $am == 'both') {
        set_setting('google_client_id', trim($_POST['google_client_id']), $dbc);
        set_setting('google_client_secret', trim($_POST['google_client_secret']), $dbc);
    }
    $auth_update_msg = "<div class='alert alert-success mt-2'>Authentication settings updated.</div>";
}
$auth_method = get_setting('auth_method', $dbc) ?: 'local';
$google_client_id = get_setting('google_client_id', $dbc) ?: '';
$google_client_secret = get_setting('google_client_secret', $dbc) ?: '';

// ----------- User Management -----------
$user_update_msg = null;
if (isset($_POST['edit_user_id'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $edit_name = mysqli_real_escape_string($dbc, $_POST['edit_user_name']);
    $edit_role = $_POST['edit_user_role'] == 'admin' ? 'admin' : 'user';
    $edit_active = $_POST['edit_user_active'] == '1' ? 1 : 0;
    $update_sql = "UPDATE CBUsers SET name='$edit_name', role='$edit_role', active=$edit_active WHERE id=$edit_id";
    mysqli_query($dbc, $update_sql);
    if (!empty($_POST['edit_user_password'])) {
        $edit_password_hash = password_hash($_POST['edit_user_password'], PASSWORD_DEFAULT);
        mysqli_query($dbc, "UPDATE CBUsers SET password='$edit_password_hash' WHERE id=$edit_id");
    }
    $user_update_msg = "<div class='alert alert-success mt-2'>User updated successfully.</div>";
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['edit_user_id'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    if ($user_id > 0) {
        if (isset($_POST['make_admin'])) {
            mysqli_query($dbc, "UPDATE CBUsers SET role='admin' WHERE id=$user_id");
        } elseif (isset($_POST['make_user'])) {
            mysqli_query($dbc, "UPDATE CBUsers SET role='user' WHERE id=$user_id");
        } elseif (isset($_POST['enable'])) {
            mysqli_query($dbc, "UPDATE CBUsers SET active=1 WHERE id=$user_id");
        } elseif (isset($_POST['disable'])) {
            mysqli_query($dbc, "UPDATE CBUsers SET active=0 WHERE id=$user_id");
        } elseif (isset($_POST['delete'])) {
            $check = mysqli_query($dbc, "SELECT email FROM CBUsers WHERE id=$user_id");
            $row = mysqli_fetch_assoc($check);
            if ($row && isset($_SESSION['email']) && $row['email'] !== $_SESSION['email']) {
                mysqli_query($dbc, "DELETE FROM CBUsers WHERE id=$user_id");
            }
        }
    }
}
$add_user_msg = null;
if (isset($_POST['add_user_email'])) {
    $email = mysqli_real_escape_string($dbc, $_POST['add_user_email']);
    $name = mysqli_real_escape_string($dbc, $_POST['add_user_name'] ?? '');
    $user_type = $_POST['add_user_type'] ?? 'local';
    $password = $_POST['add_user_password'] ?? '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = mysqli_query($dbc, "SELECT id FROM CBUsers WHERE email='$email'");
        if (mysqli_num_rows($result) == 0) {
            if ($user_type === 'local') {
                if (empty($password)) {
                    $add_user_msg = "<div class='alert alert-danger mt-2'>Password is required for local users.</div>";
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    mysqli_query($dbc, "INSERT INTO CBUsers (email, name, role, active, password) VALUES ('$email', '$name', 'user', 1, '$password_hash')");
                    $add_user_msg = "<div class='alert alert-success mt-2'>Local user created!</div>";
                }
            } else {
                mysqli_query($dbc, "INSERT INTO CBUsers (email, name, role, active, password) VALUES ('$email', '$name', 'user', 1, NULL)");
                $add_user_msg = "<div class='alert alert-success mt-2'>Google user created!</div>";
            }
        } else {
            $add_user_msg = "<div class='alert alert-warning mt-2'>A user with that email already exists.</div>";
        }
    } else {
        $add_user_msg = "<div class='alert alert-danger mt-2'>Invalid email address.</div>";
    }
}
$users = mysqli_query($dbc, "SELECT * FROM CBUsers ORDER BY role DESC, active DESC, name ASC");

// ----------- Log Viewer -----------
$logfile = '/var/log/apache2/error.log';
$log_lines = [];
if (file_exists($logfile) && is_readable($logfile)) {
    $log_lines = array_slice(file($logfile), -20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings & Admin Tools</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .card { background: #23272F; border: none; color: #EDEDED; box-shadow: 0 4px 18px #10121a22; }
        .card-title, h2, h4 { color: #ffcb4b; }
        .btn-admin { background: #ffcb4b; color: #181A20; border: none; }
        .btn-admin:hover { background: #e6b800; color: #181A20; }
        .btn-success { background: #27ae60; color: #fff; border: none; }
        .btn-success:hover { background: #219150; }
        a, .link-gold { color: #ffcb4b; }
        .form-control {
            background: #23272F;
            color: #EDEDED;
            border: 1px solid #555;
        }
        .form-control:focus {
            background: #23272F;
            color: #fff;
            border-color: #ffcb4b;
            box-shadow: none;
        }
        .alert-info { background: #323741; color: #ffcb4b; border: none;}
        .alert-success { background: #232; color: #ffe66d; border: none;}
        .alert-danger { background: #5c1b1b; color: #ffb0b0; border: none;}
        .table thead th {
            background: #23272F !important;
            color: #ffcb4b !important;
            border-bottom: 2px solid #444 !important;
        }
        .table tbody td {
            color: #EDEDED !important;
            background: #181A20 !important;
            border-color: #23272F !important;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #23272F !important;
        }
        .form-text,
        .photo-info {
            color: #ffcb4b !important;
        }
        .photo-desc {
            color: #ffd700 !important;
        }
        /* Sidebar link fixes */
        .sidebar .nav-link {
            color: #EDEDED !important;
            background: none;
            font-size: 1.12em;
            font-weight: 500;
            border-radius: 5px;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:active,
        .sidebar .nav-link:focus,
        .sidebar .nav-link:hover {
            color: #ffcb4b !important;
            background: #181A20 !important;
        }
        .sidebar .sidebar-heading {
            color: #ffcb4b !important;
            font-size: 1em;
            font-weight: 600;
            margin-top: 1.5em;
            margin-bottom: .5em;
        }
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        @media (max-width: 767px) {
            .card { padding: 0.7em 0.4em; }
            h2 { font-size: 1.1em; }
            .card-title { font-size: 1.1em; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark" style="background:#23272F;">
    <div class="container-fluid">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand ms-2" style="color:#ffcb4b; font-weight: bold;">CATS - Chromebook Asset Tracking System</span>
    </div>
</nav>
<div class="d-flex">
    <div class="sidebar-offcanvas d-none d-lg-flex flex-column p-3">
        <?php include('sidebar.php'); ?>
    </div>
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel" style="background:#23272F; color:#fff;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasSidebarLabel" style="color:#ffcb4b;">CATS Menu</h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <?php include('sidebar.php'); ?>
        </div>
    </div>
    <div class="flex-grow-1 p-2 p-md-4">

        <!-- SCHOOL NAME SETTINGS CARD -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">School Name</h4>
                <?= $school_name_msg ?? "" ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label for="school_name" class="form-label">Enter your school or district name (shown on reports, etc):</label>
                        <input type="text" name="school_name" id="school_name" class="form-control" maxlength="120"
                               value="<?= htmlspecialchars($current_school_name) ?>" required>
                    </div>
                    <button type="submit" name="update_school_name" class="btn btn-admin">Save School Name</button>
                </form>
            </div>
        </div>

        <!-- STUDENT PHOTO SETTINGS -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Student Photo Options</h4>
                <?= $student_photo_msg ?? "" ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="student_photo_enabled" name="student_photo_enabled" value="1" <?= $student_photo_enabled ? "checked" : "" ?>>
                        <label class="form-check-label" for="student_photo_enabled" style="font-size:1.14em;">Enable student photos in the system</label>
                    </div>
                    <button type="submit" name="update_photo_settings" class="btn btn-admin mb-2">Save Photo Settings</button>
                </form>
                <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2 mb-3">
                    <input type="file" name="photo_zip" accept=".zip" class="form-control" style="max-width:260px;">
                    <button type="submit" name="upload_zip" class="btn btn-admin">Upload Zip</button>
                </form>
                <a href="student_photo_upload.php" class="btn btn-success mb-2">Single Photo Upload</a>
                <div class="photo-info mt-2">
                    <span>Photos are stored in <b>student_photos/</b> on the server.<br>
                    If no photo is found for a student, a default <b>photo not available</b> image will be used.<br>
                    <span class="photo-desc">Single upload for new/corrected photos now available!</span></span>
                </div>
            </div>
        </div>

        <!-- AUTH SETTINGS -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Authentication Settings</h4>
                <?= $auth_update_msg ?? "" ?>
                <form method="post" class="row g-3" autocomplete="off">
                    <input type="hidden" name="update_auth_method" value="1">
                    <div class="col-md-4 col-12">
                        <label for="auth_method" class="form-label">Authentication Method</label>
                        <select name="auth_method" id="auth_method" class="form-select" onchange="toggleGoogleFields()" required>
                            <option value="local" <?= $auth_method == 'local' ? 'selected' : '' ?>>Local Only</option>
                            <option value="google" <?= $auth_method == 'google' ? 'selected' : '' ?>>Google Only</option>
                            <option value="both" <?= $auth_method == 'both' ? 'selected' : '' ?>>Both Local and Google</option>
                        </select>
                    </div>
                    <div id="google_fields" class="col-12" style="display:<?= ($auth_method == 'google' || $auth_method == 'both') ? 'block' : 'none' ?>;">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label for="google_client_id" class="form-label">Google Client ID</label>
                                <input type="text" name="google_client_id" id="google_client_id" class="form-control"
                                       value="<?= htmlspecialchars($google_client_id) ?>">
                            </div>
                            <div class="col-md-6 col-12">
                                <label for="google_client_secret" class="form-label">Google Client Secret</label>
                                <input type="password" name="google_client_secret" id="google_client_secret" class="form-control"
                                       value="<?= htmlspecialchars($google_client_secret) ?>">
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="auth_help.php" class="btn btn-link link-gold" style="padding-left:0">Google Setup Instructions</a>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-admin mt-2">Save Authentication Settings</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function toggleGoogleFields() {
            var val = document.getElementById('auth_method').value;
            document.getElementById('google_fields').style.display = (val === 'google' || val === 'both') ? 'block' : 'none';
        }
        document.getElementById('auth_method').addEventListener('change', toggleGoogleFields);
        </script>

        <!-- USER MANAGEMENT -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">User Management</h4>
                <?= $add_user_msg ?? "" ?>
                <?= $user_update_msg ?? "" ?>
                <p class="mb-2" style="color:#ffcb4b;">
                    <strong>Note:</strong> All users (Local or Google) must be listed below to have access.<br>
                    Set "Account Type" to "Local" for password-based users; "Google" for SSO (no password).
                </p>
                <form class="row g-3 mb-4" method="post" action="settings.php" autocomplete="off">
                    <div class="col-md-3 col-12">
                        <label for="add_user_email" class="form-label">User Email</label>
                        <input type="email" class="form-control" id="add_user_email" name="add_user_email" placeholder="Enter user email" required>
                    </div>
                    <div class="col-md-3 col-12">
                        <label for="add_user_name" class="form-label">User Name (optional)</label>
                        <input type="text" class="form-control" id="add_user_name" name="add_user_name" placeholder="Enter user name">
                    </div>
                    <div class="col-md-3 col-12">
                        <label for="add_user_type" class="form-label">Account Type</label>
                        <select class="form-select" id="add_user_type" name="add_user_type" required onchange="toggleAddUserPassword()">
                            <option value="local" selected>Local (password login)</option>
                            <option value="google">Google (SSO only)</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-12" id="add_user_password_box">
                        <label for="add_user_password" class="form-label">Password (required for Local)</label>
                        <input type="password" class="form-control" id="add_user_password" name="add_user_password" placeholder="Set password">
                    </div>
                    <div class="col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-admin w-100">Add New User</button>
                    </div>
                </form>
                <script>
                function toggleAddUserPassword() {
                    var type = document.getElementById('add_user_type').value;
                    var box = document.getElementById('add_user_password_box');
                    box.style.display = (type === 'local') ? 'block' : 'none';
                }
                document.getElementById('add_user_type').addEventListener('change', toggleAddUserPassword);
                window.onload = toggleAddUserPassword;
                </script>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $user_array = [];
                            mysqli_data_seek($users, 0);
                            while ($user = mysqli_fetch_assoc($users)):
                                $user_array[] = $user;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if (!empty($user['password'])): ?>
                                            <span class="badge bg-primary">Local</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Google</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['role']) ?></td>
                                    <td>
                                        <?php if ($user['active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['last_login'] ?? '') ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <?php if ($user['role'] == 'user'): ?>
                                                <button type="submit" name="make_admin" class="btn btn-sm btn-admin">Promote to Admin</button>
                                            <?php else: ?>
                                                <button type="submit" name="make_user" class="btn btn-sm btn-secondary">Demote to User</button>
                                            <?php endif; ?>
                                            <?php if ($user['active']): ?>
                                                <button type="submit" name="disable" class="btn btn-sm btn-warning">Disable</button>
                                            <?php else: ?>
                                                <button type="submit" name="enable" class="btn btn-sm btn-success">Enable</button>
                                            <?php endif; ?>
                                            <?php
                                            if (isset($_SESSION['email']) && $user['email'] !== $_SESSION['email']): ?>
                                                <button type="submit" name="delete" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.');">Delete</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editUserModal<?=$user['id']?>">Edit</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($user_array as $user): ?>
                <div class="modal fade" id="editUserModal<?=$user['id']?>" tabindex="-1" aria-labelledby="editUserModalLabel<?=$user['id']?>" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content bg-dark text-light">
                      <form method="post" action="settings.php">
                        <div class="modal-header">
                          <h5 class="modal-title" id="editUserModalLabel<?=$user['id']?>">Edit User: <?=htmlspecialchars($user['email'])?></h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <input type="hidden" name="edit_user_id" value="<?=$user['id']?>">
                          <div class="mb-3">
                            <label for="edit_name_<?=$user['id']?>" class="form-label">Name</label>
                            <input type="text" class="form-control" id="edit_name_<?=$user['id']?>" name="edit_user_name" value="<?=htmlspecialchars($user['name'])?>">
                          </div>
                          <?php if (!empty($user['password'])): ?>
                          <div class="mb-3">
                            <label for="edit_password_<?=$user['id']?>" class="form-label">New Password (leave blank to keep unchanged)</label>
                            <input type="password" class="form-control" id="edit_password_<?=$user['id']?>" name="edit_user_password" autocomplete="new-password">
                          </div>
                          <?php endif; ?>
                          <div class="mb-3">
                            <label for="edit_role_<?=$user['id']?>" class="form-label">Role</label>
                            <select class="form-select" id="edit_role_<?=$user['id']?>" name="edit_user_role">
                              <option value="user" <?=$user['role'] == 'user' ? 'selected' : ''?>>User</option>
                              <option value="admin" <?=$user['role'] == 'admin' ? 'selected' : ''?>>Admin</option>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label for="edit_active_<?=$user['id']?>" class="form-label">Status</label>
                            <select class="form-select" id="edit_active_<?=$user['id']?>" name="edit_user_active">
                              <option value="1" <?=$user['active'] ? 'selected' : ''?>>Active</option>
                              <option value="0" <?=!$user['active'] ? 'selected' : ''?>>Disabled</option>
                            </select>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-admin">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- SYSTEM UTILITIES -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">System Utilities</h4>
                <ul>
                    <li><a href="sync_devices.php" class="link-gold">Import/Sync Devices</a></li>
                    <li><a href="db_backup.php" class="link-gold">Database Backup</a></li>
                    <li><a href="logs.php" class="link-gold">View Logs</a></li>
                    <li><a href="about.php" class="link-gold">About/Version Info</a></li>
                </ul>
            </div>
        </div>

        <!-- LOG FILE VIEWER -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Recent Log Entries</h4>
                <?php if ($log_lines): ?>
                    <pre style="background:#181A20;color:#ccc;padding:10px;border-radius:8px;max-height:300px;overflow:auto;font-size:0.95em;"><?php echo htmlspecialchars(implode("", $log_lines)); ?></pre>
                <?php else: ?>
                    <p>No log entries found or unable to read log file.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
