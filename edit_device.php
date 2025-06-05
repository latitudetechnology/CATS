<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// Get asset_tag from query string
$asset_tag = isset($_GET['asset_tag']) ? trim($_GET['asset_tag']) : '';
if (!$asset_tag) {
    echo "<div style='color:#fff; background:#23272F; padding:1em;'>No asset tag provided. <a href='devices.php' style='color:#ffcb4b;'>Back to Devices</a></div>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number  = mysqli_real_escape_string($dbc, trim($_POST['serial_number']));
    $model          = mysqli_real_escape_string($dbc, trim($_POST['model']));
    $location       = mysqli_real_escape_string($dbc, trim($_POST['location']));
    $status         = mysqli_real_escape_string($dbc, trim($_POST['status']));
    $is_checked_out = isset($_POST['is_checked_out']) ? 1 : 0;
    $is_loaner      = isset($_POST['is_loaner']) ? 1 : 0;
    $student_id     = mysqli_real_escape_string($dbc, trim($_POST['student_id']));
    $notes          = mysqli_real_escape_string($dbc, trim($_POST['notes']));
    $charger_missing= isset($_POST['charger_missing']) ? 1 : 0;

    $q = "UPDATE CBLocal SET
            serial_number='$serial_number',
            model='$model',
            location='$location',
            status='$status',
            is_checked_out='$is_checked_out',
            is_loaner='$is_loaner',
            student_id='$student_id',
            notes='$notes',
            charger_missing='$charger_missing'
          WHERE asset_tag='".mysqli_real_escape_string($dbc, $asset_tag)."'";
    mysqli_query($dbc, $q);

    header("Location: devices.php?msg=updated");
    exit;
}

// Load device info
$q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc, $asset_tag)."'";
$res = mysqli_query($dbc, $q);
if (!$device = mysqli_fetch_assoc($res)) {
    echo "<div style='color:#fff; background:#23272F; padding:1em;'>Device not found. <a href='devices.php' style='color:#ffcb4b;'>Back to Devices</a></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Device | <?= htmlspecialchars($asset_tag) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #181A20; color: #fff; }
        .container { max-width: 700px; margin-top: 2em; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22;}
        .form-control, .form-select {
            background: #23272F; color: #fff; border: 1px solid #444;
        }
        .form-control:focus, .form-select:focus {
            border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44;
        }
        label, .card h2 { color: #ffcb4b; font-weight: 600; font-size: 1.13em;}
        .card h2 { font-size: 1.55em; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-secondary { background: #4e5258; color: #fff; }
        a.btn-secondary { color: #fff; }
        .form-check-label { color: #ffcb4b; font-weight: 500; }
        /* Sidebar link fix if you use the sidebar include */
        .sidebar .nav-link { color: #EDEDED !important; background: none; }
        .sidebar .nav-link.active,
        .sidebar .nav-link:active,
        .sidebar .nav-link:focus,
        .sidebar .nav-link:hover {
            color: #ffcb4b !important;
            background: #23272F !important;
        }
        @media (max-width: 600px) {
            .container { max-width: 99vw; padding: 0 0.5em; }
            .card { padding: 0.7em 0.3em; }
            .card h2 { font-size: 1.1em; }
            label { font-size: 1em; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h2>Edit Device: <?= htmlspecialchars($asset_tag) ?></h2>
        <form method="POST">
            <div class="mb-3">
                <label>Asset Tag</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($device['asset_tag']) ?>" readonly>
            </div>
            <div class="mb-3">
                <label>Serial Number</label>
                <input type="text" name="serial_number" class="form-control" style="color:#fff;" value="<?= htmlspecialchars($device['serial_number']) ?>" required>
            </div>
            <div class="mb-3">
                <label>Model</label>
                <input type="text" name="model" class="form-control" style="color:#fff;" value="<?= htmlspecialchars($device['model']) ?>">
            </div>
            <div class="mb-3">
                <label>Location</label>
                <input type="text" name="location" class="form-control" style="color:#fff;" value="<?= htmlspecialchars($device['location']) ?>">
            </div>
            <div class="mb-3">
                <label>Status</label>
                <select name="status" class="form-select" style="color:#fff;">
                    <?php
                    $statuses = ['active', 'retired', 'lost', 'repair', 'pending'];
                    foreach($statuses as $status) {
                        $sel = ($device['status'] === $status) ? 'selected' : '';
                        echo "<option value=\"$status\" $sel>".ucfirst($status)."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_checked_out" class="form-check-input" id="is_checked_out"
                    <?= $device['is_checked_out'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_checked_out">Checked Out</label>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_loaner" class="form-check-input" id="is_loaner"
                    <?= $device['is_loaner'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_loaner">Loaner</label>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="charger_missing" class="form-check-input" id="charger_missing"
                    <?= isset($device['charger_missing']) && $device['charger_missing'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="charger_missing">
                    Charger Missing
                </label>
                <span class="text-muted ms-2" style="font-size:0.98em;">
                    (Uncheck if charger was returned)
                </span>
            </div>
            <div class="mb-3">
                <label>Student ID (if checked out)</label>
                <input type="text" name="student_id" class="form-control" style="color:#fff;" value="<?= htmlspecialchars($device['student_id']) ?>">
            </div>
            <div class="mb-3">
                <label>Notes</label>
                <textarea name="notes" class="form-control" style="color:#fff;" rows="3"><?= htmlspecialchars($device['notes']) ?></textarea>
            </div>
            <div class="mb-3">
                <button class="btn btn-warning" type="submit"><i class="fa fa-save"></i> Save Changes</button>
                <a href="devices.php" class="btn btn-secondary ms-2"><i class="fa fa-arrow-left"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
