<?php
include('session_check.php');
include('/var/cbinfo_connect.php');

$asset_tag = isset($_GET['asset_tag']) ? trim($_GET['asset_tag']) : '';
$device = null; $cbdata = null; $history = []; $workorders = [];
$cart_name = ''; $cart_location = '';

if ($asset_tag) {
    // Device info (local)
    $q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc, $asset_tag)."' LIMIT 1";
    $res = mysqli_query($dbc, $q);
    $device = mysqli_fetch_assoc($res);

    // Get cart info if assigned
    if ($device && !empty($device['checked_out_cart'])) {
        $cart_id = intval($device['checked_out_cart']);
        $cart_q = "SELECT cart_name, location FROM CBCart WHERE cart_id=$cart_id LIMIT 1";
        $cart_res = mysqli_query($dbc, $cart_q);
        if ($cart = mysqli_fetch_assoc($cart_res)) {
            $cart_name = $cart['cart_name'];
            $cart_location = $cart['location'];
        }
    }

    // CBData info (for extra details)
    $cb_q = "SELECT * FROM cbdata WHERE annotatedAssetID='".mysqli_real_escape_string($dbc, $asset_tag)."' LIMIT 1";
    $cb_res = mysqli_query($dbc, $cb_q);
    $cbdata = mysqli_fetch_assoc($cb_res);

    // Event history (Check In/Out + Loaners)
    $ev_q = "SELECT * FROM CBEvents WHERE assignedID='".mysqli_real_escape_string($dbc, $asset_tag)."' AND Event IN ('Check In', 'Check Out', 'Loaner Checked In', 'Loaner Checked Out') ORDER BY time DESC";
    $history = mysqli_query($dbc, $ev_q);

    // Workorders
    $wo_q = "SELECT * FROM CBEvents WHERE assignedID='".mysqli_real_escape_string($dbc, $asset_tag)."' AND Event='Workorder' ORDER BY time DESC";
    $workorders = mysqli_query($dbc, $wo_q);
}

// Helper: Convert UTC time to Central Time
function utc_to_central($ts) {
    if (!$ts) return '';
    $dt = new DateTime($ts, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('America/Chicago'));
    return $dt->format('Y-m-d g:i A') . ' CT';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device Details: <?= htmlspecialchars($asset_tag) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #181A20; color: #fff; }
        .container { max-width: 950px; margin-top: 2em; }
        .card {
            background: #23272F;
            border: none;
            margin-bottom: 1.5em;
            color: #F4F4F8;
            box-shadow: 0 4px 18px #10121a22;
        }
        .device-head, h3, h4 { color: #ffcb4b; }
        .device-head { font-size:1.6em; font-weight:700; }
        .detail-label {
            color: #ffcb4b;
            font-weight: 700;
            min-width: 135px;
            display: inline-block;
            margin-right: 8px;
        }
        .device-detail-value {
            color: #fff;
            font-weight: 500;
            word-break: break-word;
        }
        .info-row {
            margin-bottom: 0.45em;
            font-size: 1.01em;
            word-break: break-word;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5em 2.2em;
        }
        @media (max-width: 900px) {
            .details-grid { grid-template-columns: 1fr; }
            .device-head { font-size:1.18em; }
        }
        .table thead th { background: #23272F; color: #ffcb4b; font-weight:700; }
        .table tbody tr { color: #fff; font-size: 0.98em;}
        a.wo-link { color:#3a80d9; text-decoration:underline; }
        a.wo-link:hover { color:#ffcb4b; }
        a.stu-link { color: #ffcb4b; text-decoration: underline; }
        a.stu-link:hover { color: #3a80d9; }
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
    </style>
</head>
<body>
<div class="container">
    <a href="devices.php" class="btn btn-secondary mb-3"><i class="fa fa-arrow-left"></i> Back to Devices</a>
    <div class="card p-4">
        <div class="device-head mb-3">Device Details: <?= htmlspecialchars($asset_tag) ?></div>
        <!-- CBData Info -->
        <?php if($cbdata): ?>
            <div class="details-grid mb-3">
                <div class="info-row"><span class="detail-label">Asset ID:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['annotatedAssetID']) ?></span></div>
                <div class="info-row"><span class="detail-label">Serial Number:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['serialNumber']) ?></span></div>
                <div class="info-row"><span class="detail-label">Last Enrollment Time:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['lastEnrollmentTime']) ?></span></div>
                <div class="info-row"><span class="detail-label">Last Internal IP:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['LastKnownNetworkInternal']) ?></span></div>
                <div class="info-row"><span class="detail-label">Last External IP:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['LastKnownNetworkExternal']) ?></span></div>
                <div class="info-row"><span class="detail-label">Last Sync Time:</span> <span class="device-detail-value"><?= utc_to_central($cbdata['lastSync']) ?></span></div>
                <div class="info-row"><span class="detail-label">MAC Address:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['macAddress']) ?></span></div>
                <div class="info-row"><span class="detail-label">Model:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['model']) ?></span></div>
                <div class="info-row"><span class="detail-label">ORG:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['orgUnitPath']) ?></span></div>
                <div class="info-row"><span class="detail-label">OS Version:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['osVersion']) ?></span></div>
                <div class="info-row"><span class="detail-label">Recent User:</span> <span class="device-detail-value"><?= htmlspecialchars($cbdata['recentUsersemail1']) ?></span></div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-3">No Google device info (cbdata) found for this asset tag.</div>
        <?php endif; ?>

        <!-- CBLocal Info (as before, but after cbdata block) -->
        <?php if(!$device): ?>
            <div class="alert alert-danger">Device not found in local database.</div>
        <?php else: ?>
            <div class="details-grid mb-2">
                <div class="info-row"><span class="detail-label">Model:</span> <span class="device-detail-value"><?= htmlspecialchars($device['model']) ?></span></div>
                <div class="info-row"><span class="detail-label">Serial Number:</span> <span class="device-detail-value"><?= htmlspecialchars($device['serial_number']) ?></span></div>
                <div class="info-row"><span class="detail-label">Location:</span> <span class="device-detail-value"><?= htmlspecialchars($device['location']) ?></span></div>
                <!-- Cart Info -->
                <div class="info-row">
                    <span class="detail-label">Cart:</span>
                    <span class="device-detail-value">
                        <?= htmlspecialchars($cart_name) ?>
                        <?= ($cart_name && $cart_location) ? " ({$cart_location})" : "" ?>
                    </span>
                </div>
                <div class="info-row"><span class="detail-label">Status:</span> <span class="device-detail-value"><?= htmlspecialchars($device['status']) ?></span></div>
                <div class="info-row"><span class="detail-label">Checked Out:</span> <span class="device-detail-value"><?= $device['is_checked_out'] ? "Yes" : "No" ?></span></div>
                <div class="info-row"><span class="detail-label">Loaner:</span> <span class="device-detail-value"><?= $device['is_loaner'] ? "Yes" : "No" ?></span></div>
                <div class="info-row">
                    <span class="detail-label">Charger Status:</span>
                    <?php if (isset($device['charger_missing']) && $device['charger_missing']): ?>
                        <span class="device-detail-value charger-missing-yes">Missing</span>
                    <?php else: ?>
                        <span class="device-detail-value charger-missing-no">Returned</span>
                    <?php endif; ?>
                </div>
                <?php
                $student_name = '';
                if (!empty($device['student_id'])) {
                    $stu_id = mysqli_real_escape_string($dbc, $device['student_id']);
                    $stu_res = mysqli_query($dbc, "SELECT firstName, lastName FROM CATstudent WHERE studentID='$stu_id' LIMIT 1");
                    if ($stu = mysqli_fetch_assoc($stu_res)) {
                        $student_name = $stu['firstName'] . ' ' . $stu['lastName'];
                    }
                }
                if (!empty($student_name)) : ?>
                    <div class="info-row">
                        <span class="detail-label">Current Student:</span>
                        <span class="device-detail-value">
                            <a href="student_details.php?student_id=<?= urlencode($device['student_id']) ?>" class="stu-link">
                                <?= htmlspecialchars($student_name) ?> (<?= htmlspecialchars($device['student_id']) ?>)
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if(!empty($device['notes'])): ?>
                    <div class="info-row">
                        <span class="detail-label">Notes:</span>
                        <span class="device-detail-value"><?= htmlspecialchars($device['notes']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Check In/Out History -->
    <div class="card p-4">
        <h4 class="mb-3">Check In / Check Out History</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Event</th>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($history)>0): ?>
                        <?php while($ev = mysqli_fetch_assoc($history)) { ?>
                            <tr>
                                <td><?= htmlspecialchars(utc_to_central($ev['time'])) ?></td>
                                <td class="history-label"><?= htmlspecialchars($ev['Event']) ?></td>
                                <td><?= htmlspecialchars($ev['studentName'] ?? '') ?></td>
                                <td><?= htmlspecialchars($ev['studentID'] ?? '') ?></td>
                                <td><?= htmlspecialchars($ev['status'] ?? '') ?></td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No check in/out history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Workorder History -->
    <div class="card p-4">
        <h4 class="mb-3">Workorder History</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th>Student</th>
                        <th>Description</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($workorders)>0): ?>
                        <?php while($wo = mysqli_fetch_assoc($workorders)) { ?>
                            <tr>
                                <td><?= htmlspecialchars(utc_to_central($wo['time'])) ?></td>
                                <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$wo['status']))) ?></td>
                                <td><?= htmlspecialchars($wo['studentName']) ?></td>
                                <td><?= htmlspecialchars($wo['wo_part']) ?><?= !empty($wo['wo_comment']) ? (" — ".htmlspecialchars($wo['wo_comment'])) : "" ?></td>
                                <td>
                                    <a href="workorder_edit.php?id=<?= urlencode($wo['id']) ?>" class="wo-link">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No workorders found for this device.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
