<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// Stat cards (Workorders from CBEvents, loaner/devices from CBLocal)
$untouched_q = "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND (notes IS NULL OR notes='') AND (ServiceLoc IS NULL OR ServiceLoc='')";
$untouched_count = mysqli_fetch_row(mysqli_query($dbc, $untouched_q))[0];

$local_q = "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND ServiceLoc='Local'";
$local_count = mysqli_fetch_row(mysqli_query($dbc, $local_q))[0];

$shipped_q = "SELECT COUNT(*) FROM CBEvents WHERE Event='Workorder' AND ServiceLoc='Ship'";
$shipped_count = mysqli_fetch_row(mysqli_query($dbc, $shipped_q))[0];

// Loaner Devices (from CBLocal), lookup student name and most recent loaner reason for each
$loaners_q = "SELECT asset_tag, student_id FROM CBLocal WHERE is_loaner=1 AND is_checked_out=1";
$loaners_res = mysqli_query($dbc, $loaners_q);
$loaners = [];
while ($row = mysqli_fetch_assoc($loaners_res)) {
    // Find student name
    $student_name = '';
    if (!empty($row['student_id'])) {
        $stu_id = mysqli_real_escape_string($dbc, $row['student_id']);
        $stu_res = mysqli_query($dbc, "SELECT firstName, lastName FROM CATstudent WHERE studentID='$stu_id' LIMIT 1");
        if ($stu = mysqli_fetch_assoc($stu_res)) {
            $student_name = $stu['firstName'] . ' ' . $stu['lastName'];
        }
    }
    // Get latest loaner reason from CBEvents (Loaner Checked Out)
    $tag = mysqli_real_escape_string($dbc, $row['asset_tag']);
    $reason_q = "SELECT loaner_reason, loaner_reason_other FROM CBEvents WHERE assignedID='$tag' AND Event='Loaner Checked Out' ORDER BY time DESC LIMIT 1";
    $reason_res = mysqli_query($dbc, $reason_q);
    $reason_row = mysqli_fetch_assoc($reason_res);
    $reason = $reason_row['loaner_reason'] ?? '';
    if ($reason == 'Other' && !empty($reason_row['loaner_reason_other'])) {
        $reason = $reason_row['loaner_reason_other'];
    }
    $row['student_name'] = $student_name;
    $row['reason'] = $reason;
    $loaners[] = $row;
}

// Recent Open Workorders (from CBEvents)
$workorder_q = "SELECT id, ServiceLoc, time, assignedID, damage, studentName, status
    FROM CBEvents WHERE Event='Workorder' AND (status IS NULL OR status != 'completed')
    ORDER BY time DESC LIMIT 10";
$workorders = mysqli_query($dbc, $workorder_q);

// Get last refresh time
$refresh_time = '';
$refresh_q = mysqli_query($dbc, "SELECT last_refresh FROM data_refresh_log WHERE id=1");
if ($refresh_row = mysqli_fetch_assoc($refresh_q)) {
    if ($refresh_row['last_refresh']) {
        $dt = new DateTime($refresh_row['last_refresh'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Chicago'));
        $refresh_time = $dt->format('F j, Y, g:i a T');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chromebook Asset Tracking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .card {
            background: #23272F;
            border: none;
            box-shadow: 0 4px 18px #10121a22;
        }
        .stat-card { 
            background: #23272F; 
            color: #fff; 
            border-radius: 16px; 
            padding: 24px 16px; 
            text-align: center; 
            margin-bottom: 18px; 
            box-shadow: 0 4px 24px rgba(0,0,0,0.14);
            transition: box-shadow 0.15s, transform 0.15s;
        }
        .stat-card.yellow { background: #ffcb4b; color: #181A20; }
        .card-title, h3, h4, label { color: #ffcb4b !important; }
        .form-control {
            background: #21232b;
            color: #fff;
            border: 1px solid #555;
            font-size: 1.13em;
        }
        .form-control:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.08rem #ffcb4b66; }
        .form-control::placeholder { color: #EDEDED !important; opacity: 1; }
        .card { max-width: 1100px; margin: 0 auto; width: 100%; }
        .table thead th {
            background: #23272F;
            color: #ffcb4b !important;
            font-weight: 700;
            font-size: 1.13em;
            border-bottom: 2px solid #555;
        }
        .table tbody tr { color: #fff; font-size: 1.13em; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #252834 !important; }
        .btn, .form-control { font-size: 1.11rem; }
        .navbar-toggler { border: none; }
        .navbar-toggler:focus { outline: none; box-shadow: none; }
        .sidebar-offcanvas { min-width:220px; background:#23272F; color:#fff; height:100vh; }
        .sidebar h3, .sidebar-heading { color: #ffcb4b; }
        .stat-link {
            text-decoration: none !important;
            display: block;
        }
        .stat-link .stat-card:hover, .stat-link:focus .stat-card {
            box-shadow: 0 0 0 4px #ffcb4b88, 0 4px 24px rgba(0,0,0,0.18);
            cursor: pointer;
            transform: scale(1.03);
            z-index: 1;
        }
        .btn-edit, .btn-details {
            font-size: 1em;
            margin-left: 6px;
            padding: 2px 8px;
        }
        .btn-edit { background: #4e5258; color: #ffcb4b; border: none;}
        .btn-edit:hover { background: #ffcb4b; color: #181A20;}
        .btn-details { background: #5a95e6; color: #fff; border: none;}
        .btn-details:hover { background: #3975c9; color: #fff;}
        a.btn-details, a.btn-edit { text-decoration: none; }
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        @media (max-width: 767px) {
            .card { padding: 0.7em 0.4em; }
            .card-title, h3, h4 { font-size:1.09em;}
            .stat-card .stat-number { font-size: 1.4rem; }
            .table thead th, .table tbody td { font-size: 0.97em; }
        }
        /* Pagination */
        .pagination .page-link { background: #23272F; color: #fff; border: 1px solid #444; }
        .pagination .page-item.active .page-link { background: #ffcb4b; color: #181A20; border: 1px solid #ffcb4b; }
        /* SIDEBAR FIX */
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
        .modal-content { background: #23272F; color: #fff; }
        .btn-success { background: #27ae60; color: #fff; border: none; }
        .btn-success:hover { background: #219150; }
    </style>
</head>
<body>
<!-- Top navbar for mobile -->
<nav class="navbar navbar-dark" style="background:#23272F;">
    <div class="container-fluid">
        <!-- Hamburger for mobile -->
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
        <!-- Stat Cards -->
        <div class="row mb-4 g-2">
            <div class="col-12 col-md-4">
                <a href="workorders.php?status=new" class="stat-link">
                    <div class="stat-card yellow">
                        <div class="stat-number"><?= $untouched_count ?? 0 ?></div>
                        <div>New/Untouched Workorders</div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="workorders.php?status=in_progress" class="stat-link">
                    <div class="stat-card">
                        <div class="stat-number"><?= $local_count ?? 0 ?></div>
                        <div>In Progress (Local)</div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="workorders.php?status=shipped" class="stat-link">
                    <div class="stat-card">
                        <div class="stat-number"><?= $shipped_count ?? 0 ?></div>
                        <div>Shipped for Repair</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Loaner Devices Table -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Loaner Devices Currently Checked Out</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Loaner Asset Tag</th>
                                <th>Student Name</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($loaners) > 0): ?>
                                <?php foreach($loaners as $row) { ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($row['asset_tag']) ?>
                                            <a href="device_details.php?asset_tag=<?= urlencode($row['asset_tag']) ?>" class="btn btn-details btn-sm" title="View Device"><i class="fa fa-laptop"></i></a>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['student_name']) ?>
                                            <?php if (!empty($row['student_id'])): ?>
                                                <a href="student_details.php?student_id=<?= urlencode($row['student_id']) ?>" class="btn btn-details btn-sm" title="View Student"><i class="fa fa-user"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['reason']) ?></td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No loaners currently checked out.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Open Workorders Table -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Recent Open Workorders</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Time</th>
                                <th>Device</th>
                                <th>Part</th>
                                <th>Student Name</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($workorders) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($workorders)) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$row['status']))) ?></td>
                                        <td><?= htmlspecialchars($row['ServiceLoc']) ?></td>
                                        <td><?= htmlspecialchars($row['time']) ?></td>
                                        <td><?= htmlspecialchars($row['assignedID']) ?></td>
                                        <td><?= htmlspecialchars($row['damage']) ?></td>
                                        <td><?= htmlspecialchars($row['studentName']) ?></td>
                                        <td>
                                            <a href="workorder_edit.php?id=<?= urlencode($row['id']) ?>" class="btn btn-edit btn-sm" title="Edit Workorder"><i class="fa fa-edit"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No open workorders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- External Database Refresh Info -->
        <div class="text-end mt-3" style="color:#ffcb4b; font-size:1.09em;">
            <i class="fa fa-database"></i>
            External Databases last updated:
            <span style="font-weight:600;">
                <?= $refresh_time ? htmlspecialchars($refresh_time) : 'Unknown' ?>
            </span>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
