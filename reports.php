<?php
include('session_check.php');

if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// Workorder Stats
$total_workorders = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder'"))[0];
$completed_workorders = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND status='completed'"))[0];
$shipped_workorders = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND status='shipped'"))[0];

// Damage Area Stats
$damage_types = ["LCD", "Keyboard", "Case/Body", "Software/No Boot", "Power Cord", "Other"];
$damage_counts = [];
foreach ($damage_types as $type) {
    $q = "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND damageArea='" . mysqli_real_escape_string($dbc, $type) . "'";
    $damage_counts[$type] = mysqli_fetch_row(mysqli_query($dbc, $q))[0];
}

// Type of Damage Stats (for pie chart)
$damage_kinds = ['Defective', 'Accidental', 'Intentional', 'Missing', 'Vandalism'];
$damage_kind_counts = [];
foreach ($damage_kinds as $kind) {
    $q = "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND damage='" . mysqli_real_escape_string($dbc, $kind) . "'";
    $damage_kind_counts[$kind] = mysqli_fetch_row(mysqli_query($dbc, $q))[0];
}

// Charger Missing Report Stats
$missing_charger_count = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBLocal WHERE charger_missing=1"))[0];
$returned_charger_count = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBLocal WHERE charger_missing=0"))[0];
$total_devices = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM CBLocal"))[0];

// Charger Details
$show_charger = isset($_GET['report']) && $_GET['report'] === 'charger';
if ($show_charger) {
    $charger_devices_q = "SELECT asset_tag, serial_number, location, student_id, charger_missing FROM CBLocal WHERE charger_missing=1 ORDER BY asset_tag ASC LIMIT 250";
    $charger_devices = mysqli_query($dbc, $charger_devices_q);
}

// Devices Checked Out / Not Checked Out Reports
$show_checked_out = isset($_GET['report']) && $_GET['report'] === 'checkedout';
$show_not_checked_out = isset($_GET['report']) && $_GET['report'] === 'notcheckedout';

if ($show_checked_out) {
    $checked_out_q = "
        SELECT l.asset_tag, l.serial_number, l.student_id, s.firstName, s.lastName, s.grade
        FROM CBLocal l
        LEFT JOIN CATstudent s ON l.student_id = s.studentID
        WHERE l.is_checked_out = 1
        ORDER BY l.asset_tag ASC
        LIMIT 500";
    $checked_out_devices = mysqli_query($dbc, $checked_out_q);

    // CSV Export
    if (isset($_GET['download']) && $_GET['download'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="devices_checked_out.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Asset Tag', 'Serial Number', 'Student Name', 'Grade']);
        mysqli_data_seek($checked_out_devices, 0);
        while ($dev = mysqli_fetch_assoc($checked_out_devices)) {
            fputcsv($out, [
                $dev['asset_tag'],
                $dev['serial_number'],
                trim($dev['firstName'] . ' ' . $dev['lastName']),
                $dev['grade']
            ]);
        }
        fclose($out);
        exit;
    }
}

if ($show_not_checked_out) {
    $not_checked_out_q = "
        SELECT asset_tag, serial_number, location
        FROM CBLocal
        WHERE is_checked_out = 0
        ORDER BY asset_tag ASC
        LIMIT 500";
    $not_checked_out_devices = mysqli_query($dbc, $not_checked_out_q);

    // CSV Export
    if (isset($_GET['download']) && $_GET['download'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="devices_not_checked_out.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Asset Tag', 'Serial Number', 'Location']);
        mysqli_data_seek($not_checked_out_devices, 0);
        while ($dev = mysqli_fetch_assoc($not_checked_out_devices)) {
            fputcsv($out, [
                $dev['asset_tag'],
                $dev['serial_number'],
                $dev['location']
            ]);
        }
        fclose($out);
        exit;
    }
}

// Inactive Devices Report Section
$inactive_periods = [
    '1m' => '-1 month',
    '3m' => '-3 months',
    '6m' => '-6 months',
    '1y' => '-1 year',
    '2y' => '-2 years',
    '3y' => '-3 years'
];
$inactive_labels = [
    '1m' => '1 Month',
    '3m' => '3 Months',
    '6m' => '6 Months',
    '1y' => '1 Year',
    '2y' => '2 Years',
    '3y' => '3 Years'
];
$inactive_selected = isset($_GET['inactive']) && array_key_exists($_GET['inactive'], $inactive_periods) ? $_GET['inactive'] : '';
$inactive_devices = [];

if ($inactive_selected) {
    $cutoff = date('Y-m-d H:i:s', strtotime($inactive_periods[$inactive_selected]));
    // Only report devices still ACTIVE locally
    $q = "SELECT cbdata.annotatedAssetID, cbdata.serialNumber, cbdata.model, cbdata.lastSync, cbdata.orgUnitPath
          FROM cbdata
          JOIN CBLocal ON cbdata.annotatedAssetID = CBLocal.asset_tag
          WHERE (cbdata.lastSync IS NULL OR cbdata.lastSync < '$cutoff')
            AND cbdata.annotatedAssetID <> ''
            AND CBLocal.status = 'active'";
    $res = mysqli_query($dbc, $q);
    while ($row = mysqli_fetch_assoc($res)) {
        $inactive_devices[] = $row;
    }

    // CSV Download
    if (isset($_GET['download']) && $_GET['download'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="inactive_devices_' . $inactive_selected . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Asset Tag', 'Serial Number', 'Model', 'Last Sync', 'Org Unit']);
        foreach ($inactive_devices as $dev) {
            fputcsv($out, [
                $dev['annotatedAssetID'],
                $dev['serialNumber'],
                $dev['model'],
                $dev['lastSync'],
                $dev['orgUnitPath']
            ]);
        }
        fclose($out);
        exit;
    }
}

// Workorder Table (all, or filtered by damage area)
$filter_damage = isset($_GET['damage']) ? $_GET['damage'] : '';
$where = "WHERE Event='Workorder'";
if ($filter_damage && in_array($filter_damage, $damage_types)) {
    $where .= " AND damageArea='" . mysqli_real_escape_string($dbc, $filter_damage) . "'";
}
$workorders_q = "SELECT * FROM CBEvents $where ORDER BY time DESC LIMIT 100";
$workorders = mysqli_query($dbc, $workorders_q);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Statistics | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #181A20; color: #EDEDED; }
        h2, h3, h4, .card-title { color: #ffcb4b; }
        .card { background: #23272F; border: none; color: #EDEDED; box-shadow: 0 4px 18px #10121a22; }
        .table thead th {
            background: #23272F;
            color: #ffcb4b !important;
            font-weight: bold;
        }
        .table tbody td {
            color: #EDEDED !important;
            background: #181A20 !important;
            border-color: #23272F !important;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #23272F !important;
        }
        .btn-warning, .btn-warning:visited {
            background: #ffcb4b !important;
            color: #181A20 !important;
            border: none;
            font-weight: bold;
        }
        .btn-warning:hover { background: #ffd900 !important; }
        .card-title, .section-label { color: #ffcb4b; }
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
        .charger-missing-yes {
            color: #fff;
            background: #c0392b;
            border-radius: 6px;
            padding: 1px 10px;
            font-weight: bold;
        }
        .charger-missing-no {
            color: #fff;
            background: #27ae60;
            border-radius: 6px;
            padding: 1px 10px;
            font-weight: bold;
        }
        .reports-row { display: flex; flex-wrap: wrap; align-items: flex-start; }
        .reports-col { flex: 0 0 220px; min-width: 160px; max-width: 260px; margin-right: 1em; }
        .chart-card {
            padding: 10px 10px 2px 10px;
            min-width: 220px;
            max-width: 420px;
        }
        .chart-card canvas {
            width: 100% !important;
            max-width: 320px;
            height: 220px !important;
            max-height: 220px;
            margin: 0 auto;
            display: block;
        }
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
            .chart-card, .chart-card canvas { max-width: 100%; min-width: 120px;}
            .reports-col { max-width: 95vw; min-width: 140px;}
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        @media (max-width: 767px) {
            .card { padding: 0.7em 0.4em; }
            h2 { font-size: 1.15em; }
            .card-title { font-size: 1.1em; }
            .reports-row { flex-direction: column; }
            .reports-col { margin-bottom: 1.5em; }
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

    <!-- Main Content -->
    <div class="flex-grow-1 p-2 p-md-4">
        <h2 class="mb-4">Reports & Statistics</h2>
        <div class="d-flex reports-row gap-4">
            <!-- Stats Column -->
            <div class="reports-col" style="width:100%;">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Workorder Stats</h4>
                        <ul class="mb-2">
                            <li>Total Workorders: <span style="color:#ffcb4b;"><?= intval($total_workorders) ?></span></li>
                            <li>Completed: <span style="color:#ffcb4b;"><?= intval($completed_workorders) ?></span></li>
                            <li>Shipped for Repair: <span style="color:#ffcb4b;"><?= intval($shipped_workorders) ?></span></li>
                        </ul>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">By Damage Area</h4>
                        <ul class="mb-0">
                            <?php foreach ($damage_types as $type): ?>
                                <li>
                                    <?= htmlspecialchars($type) ?> <span style="color:#ffcb4b;">(<?= intval($damage_counts[$type]) ?>)</span>
                                    <a href="reports.php?damage=<?= urlencode($type) ?>" class="btn btn-warning btn-sm ms-2 mb-1">View</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if($filter_damage): ?>
                            <div class="mt-3">
                                <a href="reports.php" class="btn btn-secondary btn-sm">Show All Workorders</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Charger Report Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Charger Status</h4>
                        <ul class="mb-2">
                            <li>Missing Charger: <span class="charger-missing-yes"> <?= intval($missing_charger_count) ?> </span></li>
                            <li>Returned: <span class="charger-missing-no"> <?= intval($returned_charger_count) ?> </span></li>
                            <li>Total Devices: <span style="color:#ffcb4b;"> <?= intval($total_devices) ?> </span></li>
                        </ul>
                        <a href="reports.php?report=charger" class="btn btn-warning btn-sm mt-2">View Details</a>
                    </div>
                </div>
                <!-- New Device Checkout Reports Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Device Checkout Status</h4>
                        <ul class="mb-2">
                            <li>
                                <a href="reports.php?report=checkedout" class="btn btn-warning btn-sm mb-1 w-100">Show Devices Checked Out</a>
                            </li>
                            <li>
                                <a href="reports.php?report=notcheckedout" class="btn btn-warning btn-sm mb-1 w-100">Show Devices Not Checked Out</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- Inactive Devices Report Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Inactive Devices</h4>
                        <form method="get" action="reports.php" class="mb-2">
                            <label for="inactive" class="form-label">Show devices not synced for:</label>
                            <select name="inactive" id="inactive" class="form-select mb-2" style="max-width: 180px; display:inline-block;">
                                <option value="">-- Select --</option>
                                <?php foreach($inactive_labels as $key => $label): ?>
                                    <option value="<?= $key ?>"<?= ($inactive_selected === $key ? ' selected' : '') ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-warning btn-sm">Run</button>
                            <?php if($inactive_selected): ?>
                                <a href="reports.php?inactive=<?= urlencode($inactive_selected) ?>&download=csv" class="btn btn-success btn-sm ms-1"><i class="fa fa-download"></i> Download CSV</a>
                            <?php endif; ?>
                        </form>
                        <?php if($inactive_selected): ?>
                            <div class="mt-2" style="font-size:0.98em;">
                                Found <span style="color:#ffcb4b;"><?= count($inactive_devices) ?></span> device(s) that have not synced for <strong><?= htmlspecialchars($inactive_labels[$inactive_selected]) ?></strong> and are still active.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Workorder Table or Charger Table or Inactive Devices Table or Checked Out Reports -->
            <div class="flex-grow-1">
            <?php if($show_charger): ?>
                <!-- ... (charger missing table code unchanged) ... -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Devices with Missing Chargers</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Serial Number</th>
                                        <th>Location</th>
                                        <th>Student Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($charger_devices)>0): ?>
                                        <?php while($dev = mysqli_fetch_assoc($charger_devices)) {
                                            $student_name = '';
                                            if (!empty($dev['student_id'])) {
                                                $sid = mysqli_real_escape_string($dbc, $dev['student_id']);
                                                $stu_res = mysqli_query($dbc, "SELECT firstName, lastName FROM CATstudent WHERE studentID='$sid' LIMIT 1");
                                                if ($stu = mysqli_fetch_assoc($stu_res)) {
                                                    $student_name = $stu['firstName'] . ' ' . $stu['lastName'];
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dev['asset_tag']) ?></td>
                                                <td><?= htmlspecialchars($dev['serial_number']) ?></td>
                                                <td><?= htmlspecialchars($dev['location']) ?></td>
                                                <td><?= htmlspecialchars($student_name) ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No devices currently marked as missing charger.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2" style="font-size:0.93em;">
                            Showing up to 250 devices with missing charger.
                        </div>
                        <div class="mt-3">
                            <a href="reports.php" class="btn btn-secondary btn-sm">Back to Reports</a>
                        </div>
                    </div>
                </div>
            <?php elseif($show_checked_out): ?>
                <!-- ... (checked out devices table unchanged) ... -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Devices Checked Out</h4>
                        <div class="mb-2">
                            <a href="reports.php?report=checkedout&download=csv" class="btn btn-success btn-sm"><i class="fa fa-download"></i> Download CSV</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Serial Number</th>
                                        <th>Student Name</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($checked_out_devices)>0): ?>
                                        <?php while($dev = mysqli_fetch_assoc($checked_out_devices)) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dev['asset_tag']) ?></td>
                                                <td><?= htmlspecialchars($dev['serial_number']) ?></td>
                                                <td><?= htmlspecialchars(trim($dev['firstName'] . ' ' . $dev['lastName'])) ?></td>
                                                <td><?= htmlspecialchars($dev['grade']) ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No devices currently checked out.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <a href="reports.php" class="btn btn-secondary btn-sm">Back to Reports</a>
                        </div>
                    </div>
                </div>
            <?php elseif($show_not_checked_out): ?>
                <!-- ... (not checked out devices table unchanged) ... -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Devices Not Checked Out</h4>
                        <div class="mb-2">
                            <a href="reports.php?report=notcheckedout&download=csv" class="btn btn-success btn-sm"><i class="fa fa-download"></i> Download CSV</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Serial Number</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($not_checked_out_devices)>0): ?>
                                        <?php while($dev = mysqli_fetch_assoc($not_checked_out_devices)) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dev['asset_tag']) ?></td>
                                                <td><?= htmlspecialchars($dev['serial_number']) ?></td>
                                                <td><?= htmlspecialchars($dev['location']) ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center">No devices available (not checked out).</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <a href="reports.php" class="btn btn-secondary btn-sm">Back to Reports</a>
                        </div>
                    </div>
                </div>
            <?php elseif($inactive_selected): ?>
                <!-- ... (inactive devices table unchanged) ... -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Inactive Devices (<?= htmlspecialchars($inactive_labels[$inactive_selected]) ?>)</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Serial Number</th>
                                        <th>Model</th>
                                        <th>Last Sync</th>
                                        <th>Org Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($inactive_devices)>0): ?>
                                        <?php foreach ($inactive_devices as $dev): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dev['annotatedAssetID']) ?></td>
                                                <td><?= htmlspecialchars($dev['serialNumber']) ?></td>
                                                <td><?= htmlspecialchars($dev['model']) ?></td>
                                                <td><?= htmlspecialchars($dev['lastSync']) ?></td>
                                                <td><?= htmlspecialchars($dev['orgUnitPath']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center">No inactive devices found for this period.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2" style="font-size:0.93em;">
                            Showing up to <?= count($inactive_devices) ?> inactive devices. <br>
                            <a href="reports.php" class="btn btn-secondary btn-sm mt-2">Back to Reports</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Pie charts for Damage Area and Type -->
                <div class="row mb-4">
                    <div class="col-12 col-md-6 mb-3">
                        <div class="card chart-card">
                            <div class="card-body pb-1">
                                <h5 class="card-title mb-2">Damage By Area</h5>
                                <canvas id="damageAreaChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <div class="card chart-card">
                            <div class="card-body pb-1">
                                <h5 class="card-title mb-2">Type of Damage</h5>
                                <canvas id="damageTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ... (default workorder table, unchanged) ... -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">
                            <?php if($filter_damage): ?>
                                Workorders - <?= htmlspecialchars($filter_damage) ?>
                            <?php else: ?>
                                All Workorders
                            <?php endif; ?>
                        </h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Asset Tag</th>
                                        <th>Student</th>
                                        <th>Damage</th>
                                        <th>Area</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($workorders)>0): ?>
                                        <?php while($row = mysqli_fetch_assoc($workorders)) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['time'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($row['assignedID'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($row['studentName'] ?? '') ?> <?= htmlspecialchars($row['studentID'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($row['damage'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($row['damageArea'] ?? '') ?></td>
                                                <td><?= htmlspecialchars(ucwords($row['status'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center">No workorders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2" style="font-size:0.93em;">
                            Showing up to 100 most recent workorders.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pie Chart: Damage by Area
    if(document.getElementById('damageAreaChart')) {
        new Chart(document.getElementById('damageAreaChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_values($damage_types)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($damage_counts)) ?>,
                    backgroundColor: [
                        "#ffcb4b","#9ad0f5","#e6657a","#3ad29f","#9a7ef5","#cccccc"
                    ],
                }]
            },
            options: {
                plugins: {
                    legend: { labels: { color: "#EDEDED", font: { weight: "bold" } } }
                }
            }
        });
    }

    // Pie Chart: Type of Damage
    if(document.getElementById('damageTypeChart')) {
        new Chart(document.getElementById('damageTypeChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($damage_kinds) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($damage_kind_counts)) ?>,
                    backgroundColor: [
                        "#36d99b","#ffcb4b","#e6657a","#9ad0f5","#a8742c"
                    ],
                }]
            },
            options: {
                plugins: {
                    legend: { labels: { color: "#EDEDED", font: { weight: "bold" } } }
                }
            }
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
