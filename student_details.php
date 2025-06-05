<?php
include('session_check.php');
include('/var/cbinfo_connect.php');

// Helper: Convert UTC to Central Time
function utc_to_central($ts) {
    if (!$ts) return '';
    $dt = new DateTime($ts, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('America/Chicago'));
    return $dt->format('Y-m-d g:i A') . ' CT';
}

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$student = null; $devices = []; $history = []; $workorders = [];
if ($student_id) {
    // Get student info
    $q = "SELECT * FROM CATstudent WHERE studentID='".mysqli_real_escape_string($dbc, $student_id)."' LIMIT 1";
    $res = mysqli_query($dbc, $q);
    $student = mysqli_fetch_assoc($res);

    if ($student) {
        // Devices ever checked out to this student
        $dev_q = "SELECT * FROM CBLocal WHERE student_id='".mysqli_real_escape_string($dbc, $student_id)."'";
        $devices = mysqli_query($dbc, $dev_q);

        // All check in/out events for this student (all devices)
        $hist_q = "SELECT * FROM CBEvents WHERE studentID='".mysqli_real_escape_string($dbc, $student_id)."' AND (Event='Check In' OR Event='Check Out') ORDER BY time DESC";
        $history = mysqli_query($dbc, $hist_q);

        // All workorders involving this student
        $wo_q = "SELECT * FROM CBEvents WHERE studentID='".mysqli_real_escape_string($dbc, $student_id)."' AND Event='Workorder' ORDER BY time DESC";
        $workorders = mysqli_query($dbc, $wo_q);
    }
}

// --- Student Photo Logic ---
$photos_enabled = 0;
$settings_q = mysqli_query($dbc, "SELECT setting_value FROM app_settings WHERE setting_key = 'student_photo_enabled' LIMIT 1");
if ($settings = mysqli_fetch_assoc($settings_q)) {
    $photos_enabled = intval($settings['setting_value']);
}

$student_photo_dir = 'student_photos/';
$student_photo_path = $student_photo_dir . $student_id . '.jpg';
$default_photo_path = $student_photo_dir . 'photo_not_available.jpg';

$photo_to_show = $default_photo_path;
if ($photos_enabled && file_exists(__DIR__ . '/' . $student_photo_path)) {
    $photo_to_show = $student_photo_path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Details: <?= htmlspecialchars($student_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        .container { max-width: 900px; margin-top: 2em; }
        .card { background: #23272F; border: none; margin-bottom: 1.5em; color: #F4F4F8; }
        .table thead th { background: #23272F; color: #fff; }
        .table tbody tr { color: #fff; }
        h3, h4, .student-head { color: #ffcb4b; }
        .student-head { font-size:1.6em; font-weight:700; }
        .detail-label { color: #ffcb4b; font-weight: 700; margin-right: 8px; }
        .student-detail-value { color: #fff; font-weight: 500; }
        a.device-link { color: #3a80d9; text-decoration: underline; }
        a.device-link:hover { color: #ffcb4b; }
        a.wo-link { color:#3a80d9; text-decoration:underline; }
        a.wo-link:hover { color:#ffcb4b; }
        .photo-box img { background: #333; }
        .photo-box { margin-bottom: 1.6em; }
    </style>
</head>
<body>
<div class="container">
    <a href="students.php" class="btn btn-secondary mb-3"><i class="fa fa-arrow-left"></i> Back to Students</a>
    <div class="card p-4">
        <div class="student-head mb-3">Student Details: <?= htmlspecialchars($student ? ($student['firstName'].' '.$student['lastName']) : $student_id) ?></div>
        <?php if(!$student): ?>
            <div class="alert alert-danger">Student not found.</div>
        <?php else: ?>
            <?php if ($photos_enabled): ?>
            <div class="photo-box text-center mb-3">
                <img src="<?= htmlspecialchars($photo_to_show) ?>"
                    alt="Student Photo"
                    style="max-width:160px; max-height:200px; border-radius:12px; box-shadow:0 2px 16px #181A2030;">
                <br>
                <span class="text-muted" style="font-size:0.98em;">
                    <?= ($photo_to_show === $default_photo_path) ? "Photo not available" : "" ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="row mb-2">
                <div class="col-md-4"><span class="detail-label">Student ID:</span> <span class="student-detail-value"><?= htmlspecialchars($student['studentID']) ?></span></div>
                <div class="col-md-4"><span class="detail-label">Lunch PIN:</span> <span class="student-detail-value"><?= htmlspecialchars($student['lunchPin']) ?></span></div>
                <div class="col-md-4"><span class="detail-label">Preferred Name:</span> <span class="student-detail-value"><?= htmlspecialchars($student['prefName']) ?></span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Devices -->
    <div class="card p-4">
        <h4 class="mb-3">Devices Assigned to Student</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Serial Number</th>
                        <th>Model</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Loaner</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($devices)>0): ?>
                        <?php while($dev = mysqli_fetch_assoc($devices)) { ?>
                            <tr>
                                <td>
                                    <a href="device_details.php?asset_tag=<?= urlencode($dev['asset_tag']) ?>" class="device-link"><?= htmlspecialchars($dev['asset_tag']) ?></a>
                                </td>
                                <td><?= htmlspecialchars($dev['serial_number']) ?></td>
                                <td><?= htmlspecialchars($dev['model']) ?></td>
                                <td><?= htmlspecialchars($dev['location']) ?></td>
                                <td><?= htmlspecialchars($dev['status']) ?></td>
                                <td><?= $dev['is_loaner'] ? "Yes" : "No" ?></td>
                                <td><?= htmlspecialchars($dev['notes']) ?></td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No devices currently assigned.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                        <th>Asset Tag</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($history)>0): ?>
                        <?php while($ev = mysqli_fetch_assoc($history)) { ?>
                            <tr>
                                <td><?= htmlspecialchars(utc_to_central($ev['time'])) ?></td>
                                <td><?= htmlspecialchars($ev['Event']) ?></td>
                                <td>
                                    <a href="device_details.php?asset_tag=<?= urlencode($ev['assignedID']) ?>" class="device-link"><?= htmlspecialchars($ev['assignedID']) ?></a>
                                </td>
                                <td><?= htmlspecialchars($ev['status']) ?></td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No check in/out history found.</td></tr>
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
                        <th>Asset Tag</th>
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
                                <td>
                                    <a href="device_details.php?asset_tag=<?= urlencode($wo['assignedID']) ?>" class="device-link"><?= htmlspecialchars($wo['assignedID']) ?></a>
                                </td>
                                <td><?= htmlspecialchars($wo['wo_part']) ?><?= !empty($wo['wo_comment']) ? (" — ".htmlspecialchars($wo['wo_comment'])) : "" ?></td>
                                <td>
                                    <a href="workorder_edit.php?id=<?= urlencode($wo['id']) ?>" class="wo-link">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No workorders found for this student.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
