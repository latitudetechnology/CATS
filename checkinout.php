<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// --- Student photo logic (reuse for both screens) ---
function get_student_photo_html($student_id, $dbc) {
    // Setting logic: read from app_settings
    $enabled = 0;
    $settings_q = mysqli_query($dbc, "SELECT setting_value FROM app_settings WHERE setting_key='student_photos_enabled' LIMIT 1");
    if ($settings = mysqli_fetch_assoc($settings_q)) {
        $enabled = intval($settings['setting_value']);
    }
    if (!$enabled) return "";

    $student_photo_dir = 'student_photos/';
    $photo_file = $student_photo_dir . $student_id . '.jpg';
    $default_photo = $student_photo_dir . 'photo_not_available.jpg';
    $full_path = __DIR__ . '/' . $photo_file;

    $show_photo = file_exists($full_path) ? $photo_file : $default_photo;
    // Light border on photo, show student id as alt
    return "<div class=\"text-center mb-2\"><img src=\"{$show_photo}?v=".time()."\" alt=\"Student Photo\" style=\"max-width:120px; max-height:160px; border-radius:9px; box-shadow:0 2px 10px #10121a22;\"><br>
    <span class=\"text-muted\" style=\"font-size:0.96em;\">".($show_photo === $default_photo ? "Photo not available" : "")."</span>
    </div>";
}

$msg = '';
$device = null;
$did_action = false;
$show_lookup = true;
$show_checkin_confirmation = false;
$show_checkout_confirmation = false;
$charger_missing = 0;

// 1st step: Lookup asset tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    $asset_tag = mysqli_real_escape_string($dbc, trim($_POST['asset_tag']));
    if ($asset_tag) {
        $device_q = "SELECT * FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1";
        $device_res = mysqli_query($dbc, $device_q);
        $device = mysqli_fetch_assoc($device_res);
        $show_lookup = false;
        if (!$device) {
            $msg = "Device not found!";
            $show_lookup = true;
        }
    }
}

// 2nd step: Action (check in/out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']))) {
    $asset_tag = mysqli_real_escape_string($dbc, trim($_POST['asset_tag']));
    $student_id = isset($_POST['student_id']) ? mysqli_real_escape_string($dbc, trim($_POST['student_id'])) : '';
    $student_name = isset($_POST['student_name']) ? mysqli_real_escape_string($dbc, trim($_POST['student_name'])) : '';
    $action = $_POST['action'];
    $charger_missing = isset($_POST['charger_missing']) ? 1 : 0;

    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);
    $show_lookup = false;

    if (!$device) {
        $msg = "Device not found!";
        $show_lookup = true;
    } elseif ($action == 'check_out') {
        if ($device['is_checked_out']) {
            $msg = "Device is already checked out to student ID: " . htmlspecialchars($device['student_id']);
        } elseif (!$student_id) {
            $msg = "Student selection required for check out. Please pick from the dropdown.";
        } else {
            $upd = "UPDATE CBLocal SET is_checked_out=1, student_id='$student_id', checked_out_date=NOW(), checked_in_date=NULL WHERE asset_tag='$asset_tag'";
            mysqli_query($dbc, $upd);
            $ev = "INSERT INTO CBEvents (Event, assignedID, studentID, studentName, status, time) VALUES ('Check Out', '$asset_tag', '$student_id', '$student_name', 'checked_out', NOW())";
            mysqli_query($dbc, $ev);
            $msg = "Checked out to $student_name ($student_id)!";
            $did_action = true;
            $show_checkout_confirmation = true;
        }
    } elseif ($action == 'check_in') {
        if (!$device['is_checked_out']) {
            $msg = "Device is not currently checked out.";
        } else {
            // Grab student info before clearing it
            $student_id_in = $device['student_id'];
            $student_name_in = '';
            if (!empty($student_id_in)) {
                $stu_id = mysqli_real_escape_string($dbc, $student_id_in);
                $stu_res = mysqli_query($dbc, "SELECT firstName, lastName FROM CATstudent WHERE studentID='$stu_id' LIMIT 1");
                if ($stu = mysqli_fetch_assoc($stu_res)) {
                    $student_name_in = $stu['firstName'] . ' ' . $stu['lastName'];
                }
            }
            $upd = "UPDATE CBLocal SET is_checked_out=0, student_id='', checked_in_date=NOW(), charger_missing=$charger_missing WHERE asset_tag='$asset_tag'";
            mysqli_query($dbc, $upd);
            // Log the check-in event WITH student info
            $ev = "INSERT INTO CBEvents (Event, assignedID, studentID, studentName, status, time, charger_missing) VALUES ('Check In', '$asset_tag', '$student_id_in', '$student_name_in', 'checked_in', NOW(), $charger_missing)";
            mysqli_query($dbc, $ev);
            $msg = "Checked in successfully!" . ($charger_missing ? " <b>(Charger not returned)</b>" : "");
            $did_action = true;
            $show_checkin_confirmation = true;
        }
    }
    // Always reload device info after action
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check In / Check Out | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; }
        .form-control { background: #23272F; color: #fff; border: 1px solid #444; }
        .form-control:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
        label { color: #ffcb4b; }
        h2, h4 { color: #ffcb4b; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-secondary { color: #fff; }
        .student-name { color: #ffcb4b; font-weight: bold; }
        .alert-info { background: #323741; color: #ffcb4b; border: none;}
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
        }
        .student-photo-box img { border-radius:8px; box-shadow: 0 2px 14px #10121a35; }
        .student-photo-box { margin-bottom: 1em; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
    $(function(){
        <?php if(!$show_lookup && $device && !$device['is_checked_out'] && !$show_checkout_confirmation): ?>
        // Autofocus on student name field after device scan, but not during confirmation
        setTimeout(function() {
            $("#student_name").focus();
        }, 150);
        <?php endif; ?>

        // Enhanced autocomplete, must select from dropdown
let selectedFromDropdown = false;
$("#student_name").autocomplete({
    source: function(request, response) {
        $.getJSON('search_student.php', {q: request.term}, function(data){
            response(data);
        });
    },
    minLength: 2,
    select: function(event, ui) {
        $("#student_name").val(ui.item.label);
        $("#student_id").val(ui.item.id);
        setTimeout(function() {
            document.getElementById('hidden_checkout_btn').click();
        }, 100);
        return false; // Prevent default behavior
    }
}).on('keydown', function(event) {
    if (event.key === "Enter") {
        if (!selectedFromDropdown) {
            event.preventDefault();
        }
    } else {
        selectedFromDropdown = false;
    }
});


        <?php if($show_checkin_confirmation || $show_checkout_confirmation): ?>
            setTimeout(function(){
                window.location = window.location.pathname;
            }, 2000);
        <?php endif; ?>
    });
    </script>
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
        <div class="card p-4 mx-auto" style="max-width: 600px;">
            <h2 class="mb-4">Device Check In / Check Out</h2>
            <?php if($msg): ?>
                <div class="alert alert-info"><?= $msg ?></div>
            <?php endif; ?>

            <!-- 1. Initial Asset Tag Lookup Form -->
            <?php if($show_lookup): ?>
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label for="asset_tag" class="form-label">Scan or Enter Asset Tag</label>
                        <input type="text" name="asset_tag" id="asset_tag"
                               class="form-control"
                               inputmode="numeric" pattern="[0-9\-]*"
                               required autofocus>
                    </div>
                    <div class="mb-3 btn-group" role="group">
                        <button type="submit" name="lookup" value="1" class="btn btn-warning">Next</button>
                    </div>
                </form>
            <!-- 2. Check Out Action Step -->
            <?php elseif($device && !$device['is_checked_out'] && !$show_checkout_confirmation): ?>
                <form id="checkinoutform" method="POST" autocomplete="off">
                    <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
                    <div class="mb-3">
                        <label for="student_name" class="form-label">Student Name (type to search, select from list)</label>
                        <input type="text" name="student_name" id="student_name" class="form-control" required autocomplete="off">
                        <input type="hidden" name="student_id" id="student_id">
                        <div class="form-text text-warning" style="color:#ffcb4b;">* Select a student from the dropdown before submitting.</div>
                    </div>
                    <button type="submit" id="hidden_checkout_btn" name="action" value="check_out" style="display:none;"></button>
                </form>
            <?php elseif($show_checkout_confirmation): ?>
                <div class="alert alert-info"><?= $msg ?></div>
            <!-- 3. Check In Action Step -->
            <?php elseif($device && $device['is_checked_out'] && !$show_checkin_confirmation): ?>
                <form id="checkinoutform" method="POST" autocomplete="off">
                    <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
                    <div class="mb-3">
                        <label class="form-label">Currently checked out to:</label>
                        <?php
                            $sid = mysqli_real_escape_string($dbc, $device['student_id']);
                            $q = "SELECT firstName, lastName, prefName FROM CATstudent WHERE studentID='$sid' LIMIT 1";
                            $res = mysqli_query($dbc, $q);
                            if ($stu = mysqli_fetch_assoc($res)) {
                                // Student photo logic
                                echo '<div class="student-photo-box">';
                                echo get_student_photo_html($sid, $dbc);
                                echo '</div>';
                                $fullname = $stu['firstName'] . " " . $stu['lastName'];
                                if ($stu['prefName']) $fullname .= " (" . $stu['prefName'] . ")";
                                echo '<div class="student-name">'.htmlspecialchars($fullname) . " [$sid]</div>";
                            } else {
                                echo htmlspecialchars($device['student_id']);
                            }
                        ?>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="charger_missing" id="charger_missing"
                            value="1" <?= isset($device['charger_missing']) && $device['charger_missing'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="charger_missing" style="font-size:1.07em;">Charger NOT returned with device</label>
                    </div>
                    <button type="submit" name="action" value="check_in" class="btn btn-success btn-lg">Check In</button>
                </form>
            <?php elseif($show_checkin_confirmation): ?>
                <div class="alert alert-info"><?= $msg ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
