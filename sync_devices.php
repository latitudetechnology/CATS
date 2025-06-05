<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// --- STEP 1: LOAD DATA ---

$cbdata_active = [];
$res = mysqli_query($dbc, "SELECT annotatedAssetID, serialNumber, model, annotaedLocation FROM cbdata WHERE status = 'ACTIVE'");
while ($row = mysqli_fetch_assoc($res)) {
    $asset_tag = trim($row['annotatedAssetID']);
    if (!$asset_tag) continue;
    $cbdata_active[$asset_tag] = [
        'serial_number' => $row['serialNumber'],
        'model' => $row['model'],
        'location' => $row['annotaedLocation']
    ];
}

// Load all from CBLocal
$cblocal = [];
$res2 = mysqli_query($dbc, "SELECT asset_tag, serial_number, model, location, status FROM CBLocal");
while ($row = mysqli_fetch_assoc($res2)) {
    $asset_tag = trim($row['asset_tag']);
    $cblocal[$asset_tag] = [
        'serial_number' => $row['serial_number'],
        'model' => $row['model'],
        'location' => $row['location'],
        'status' => $row['status']
    ];
}

// Figure out actions
$to_add = [];
$to_update = [];
foreach ($cbdata_active as $asset_tag => $info) {
    if (!isset($cblocal[$asset_tag])) {
        $to_add[$asset_tag] = $info;
    } else {
        $needs_update = false;
        foreach (['serial_number', 'model', 'location'] as $field) {
            if ($info[$field] != $cblocal[$asset_tag][$field] || $cblocal[$asset_tag]['status'] !== 'active') {
                $needs_update = true;
                break;
            }
        }
        if ($needs_update) {
            $to_update[$asset_tag] = $info;
        }
    }
}

// Devices in CBLocal not in cbdata active = mark as retired (if not already)
$to_retire = [];
foreach ($cblocal as $asset_tag => $row) {
    if (!isset($cbdata_active[$asset_tag]) && $row['status'] !== 'retired') {
        $to_retire[$asset_tag] = $row;
    }
}

// --- STEP 2: SYNC IF REQUESTED ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {
    foreach ($to_add as $asset_tag => $info) {
        $q = "INSERT INTO CBLocal (asset_tag, serial_number, model, location, status)
              VALUES (
                '".mysqli_real_escape_string($dbc, $asset_tag)."',
                '".mysqli_real_escape_string($dbc, $info['serial_number'])."',
                '".mysqli_real_escape_string($dbc, $info['model'])."',
                '".mysqli_real_escape_string($dbc, $info['location'])."',
                'active'
              )";
        mysqli_query($dbc, $q);
    }
    foreach ($to_update as $asset_tag => $info) {
        $q = "UPDATE CBLocal SET
                serial_number='".mysqli_real_escape_string($dbc, $info['serial_number'])."',
                model='".mysqli_real_escape_string($dbc, $info['model'])."',
                location='".mysqli_real_escape_string($dbc, $info['location'])."',
                status='active'
              WHERE asset_tag='".mysqli_real_escape_string($dbc, $asset_tag)."'";
        mysqli_query($dbc, $q);
    }
    if (count($to_retire) > 0) {
        $asset_tags_str = "'" . implode("','", array_map(function($tag) use ($dbc) {
            return mysqli_real_escape_string($dbc, $tag);
        }, array_keys($to_retire))) . "'";
        $q = "UPDATE CBLocal SET status='retired' WHERE asset_tag IN ($asset_tags_str)";
        mysqli_query($dbc, $q);
    }
    echo "<div style='color:#fff; background:#23272F; padding:1em;'>
        Sync complete!<br>
        Added: ".count($to_add)."<br>
        Updated: ".count($to_update)."<br>
        Marked as retired: ".count($to_retire)."<br>
        <a href='devices.php' style='color:#ffcb4b;'>View Devices</a>
        </div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sync Devices Preview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        h2, h3 { color: #ffcb4b; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; }
        .table thead th { background: #23272F; color: #ffcb4b; font-weight: 700;}
        .table tbody tr { color: #fff; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #23242e !important; }
        .table-responsive { font-size: 1.03em; }
        .sync-details { color: #ffcb4b; font-weight: 600; font-size:1.11em; margin-bottom: 0.7em; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-secondary { color: #fff; }
        /* Sidebar nav fixes for active/highlight */
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
            .table-responsive { font-size: 0.95em; }
        }
    </style>
</head>
<body>
<!-- Top navbar for mobile -->
<nav class="navbar navbar-dark" style="background:#23272F;">
    <div class="container-fluid">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand ms-2" style="color:#ffcb4b; font-weight: bold;">CATS - Chromebook Asset Tracking System</span>
    </div>
</nav>
<div class="d-flex">
    <!-- Desktop Sidebar -->
    <div class="sidebar-offcanvas d-none d-lg-flex flex-column p-3">
        <?php include('sidebar.php'); ?>
    </div>
    <!-- Offcanvas Sidebar for Mobile -->
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
        <h2>Sync Devices Preview</h2>
        <div class="card mb-3">
            <div class="card-body">
                <div class="sync-details">What will happen if you sync?</div>
                <ul style="list-style:none; padding-left:0; margin-bottom:0;">
                    <li>
                        <span style="color:#ffcb4b;"><b><?= count($to_add) ?></b></span>
                        <span style="color:#fff;"> devices will be </span>
                        <span style="color:#4eeb6b;">added</span>
                        <span style="color:#bbb;"> (new assets)</span>
                    </li>
                    <li>
                        <span style="color:#ffcb4b;"><b><?= count($to_update) ?></b></span>
                        <span style="color:#fff;"> devices will be </span>
                        <span style="color:#ffd24d;">updated</span>
                        <span style="color:#bbb;"> (existing assets changed)</span>
                    </li>
                    <li>
                        <span style="color:#ffcb4b;"><b><?= count($to_retire) ?></b></span>
                        <span style="color:#fff;"> devices will be </span>
                        <span style="color:#fb4444;">marked as retired</span>
                        <span style="color:#bbb;"> (not found in CBData)</span>
                    </li>
                </ul>
            </div>
        </div>
        <form method="POST">
            <button class="btn btn-warning btn-lg" name="sync" value="1" type="submit"><i class="fa fa-sync"></i> Sync Now</button>
            <a href="devices.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
        </form>
        <hr style="border-top:1px solid #444; margin:2em 0;">
        <div class="row">
            <div class="col-md-4">
                <h3>To Add</h3>
                <div class="table-responsive" style="max-height:300px;overflow:auto;">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr><th>Asset Tag</th><th>Serial</th><th>Model</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($to_add as $tag => $info): ?>
                            <tr>
                                <td><?= htmlspecialchars($tag) ?></td>
                                <td><?= htmlspecialchars($info['serial_number']) ?></td>
                                <td><?= htmlspecialchars($info['model']) ?></td>
                            </tr>
                        <?php endforeach; if(count($to_add)==0): ?>
                            <tr><td colspan="3">None</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <h3>To Update</h3>
                <div class="table-responsive" style="max-height:300px;overflow:auto;">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr><th>Asset Tag</th><th>Serial</th><th>Model</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($to_update as $tag => $info): ?>
                            <tr>
                                <td><?= htmlspecialchars($tag) ?></td>
                                <td><?= htmlspecialchars($info['serial_number']) ?></td>
                                <td><?= htmlspecialchars($info['model']) ?></td>
                            </tr>
                        <?php endforeach; if(count($to_update)==0): ?>
                            <tr><td colspan="3">None</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <h3>To Retire</h3>
                <div class="table-responsive" style="max-height:300px;overflow:auto;">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr><th>Asset Tag</th><th>Serial</th><th>Model</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($to_retire as $tag => $info): ?>
                            <tr>
                                <td><?= htmlspecialchars($tag) ?></td>
                                <td><?= htmlspecialchars($info['serial_number']) ?></td>
                                <td><?= htmlspecialchars($info['model']) ?></td>
                            </tr>
                        <?php endforeach; if(count($to_retire)==0): ?>
                            <tr><td colspan="3">None</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
