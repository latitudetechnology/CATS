<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// Get workorder ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo "<div style='color:#fff; background:#23272F; padding:1em;'>No workorder selected. <a href='workorders.php' style='color:#ffcb4b;'>Back to Workorders</a></div>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentID = mysqli_real_escape_string($dbc, trim($_POST['studentID']));
    $studentName = mysqli_real_escape_string($dbc, trim($_POST['studentName']));
    $damage = mysqli_real_escape_string($dbc, trim($_POST['damage']));
    $damageArea = mysqli_real_escape_string($dbc, trim($_POST['damageArea']));
    $description = mysqli_real_escape_string($dbc, trim($_POST['description']));
    $notes = mysqli_real_escape_string($dbc, trim($_POST['notes']));

    // Button logic: In Progress / Ship / Close / Save
    if (isset($_POST['inprogress'])) {
        $status = 'in_progress';
        $serviceloc = 'Local';
    } elseif (isset($_POST['ship'])) {
        $status = 'shipped';
        $serviceloc = 'Ship';
    } elseif (isset($_POST['close'])) {
        $status = 'completed';
        $serviceloc = 'Closed';
    } else {
        $status = mysqli_real_escape_string($dbc, trim($_POST['status']));
        $serviceloc = mysqli_real_escape_string($dbc, trim($_POST['ServiceLoc'] ?? $wo['ServiceLoc']));
    }

    $resolution = mysqli_real_escape_string($dbc, trim($_POST['resolution']));
    $warrantyRepair = isset($_POST['warrantyRepair']) ? 'Yes' : 'No';
    $chargeStudent = isset($_POST['chargeStudent']) ? 'Yes' : 'No';
    $Cost = $chargeStudent === 'Yes' ? floatval($_POST['Cost']) : null;

    $q = "UPDATE CBEvents SET
            studentID='$studentID',
            studentName='$studentName',
            damage='$damage',
            damageArea='$damageArea',
            description='$description',
            notes='$notes',
            status='$status',
            ServiceLoc='$serviceloc',
            resolution='$resolution',
            warrantyRepair='$warrantyRepair',
            chargeStudent='$chargeStudent',
            Cost=" . ($Cost !== null ? "'$Cost'" : "NULL") . "
          WHERE id='$id'";
    mysqli_query($dbc, $q);

    header("Location: workorders.php?msg=updated");
    exit;
}

// Load workorder info
$q = "SELECT * FROM CBEvents WHERE id='$id' AND Event='Workorder' LIMIT 1";
$res = mysqli_query($dbc, $q);
if (!$wo = mysqli_fetch_assoc($res)) {
    echo "<div style='color:#fff; background:#23272F; padding:1em;'>Workorder not found. <a href='workorders.php' style='color:#ffcb4b;'>Back to Workorders</a></div>";
    exit;
}

// Lookup serial number from CBLocal
$serial_number = '';
$assignedID = mysqli_real_escape_string($dbc, $wo['assignedID']);
$dev_res = mysqli_query($dbc, "SELECT serial_number FROM CBLocal WHERE asset_tag='$assignedID' LIMIT 1");
if ($dev = mysqli_fetch_assoc($dev_res)) {
    $serial_number = $dev['serial_number'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Workorder | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background: #181A20; color: #fff; }
        .card { background: #23272F; border: none; max-width: 700px; margin: 2em auto; box-shadow: 0 4px 18px #10121a22;}
        .form-control, .form-select { background: #23272F; color: #fff; border: 1px solid #444; }
        .form-control:focus, .form-select:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
        label { color: #ffcb4b; }
        h2 { color: #ffcb4b !important; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-primary { color: #fff; background: #377dff; border: none; }
        .btn-primary:hover { background: #245cb3; }
        .btn-success { color: #fff; background: #27ae60; border: none; }
        .btn-success:hover { background: #219150; }
        .btn-info { background: #ffd24d; color: #23272F; border: none;}
        .btn-info:hover { background: #ffe685; color: #23272F;}
        .btn-secondary { background: #4e5258; color: #fff; }
        .copy-btn {
            border: none;
            background: transparent;
            color: #ffcb4b;
            cursor: pointer;
            font-size: 1em;
            margin-left: 6px;
        }
        .copy-btn:active, .copy-btn:focus {
            outline: none;
            color: #ffe06b;
        }
        .tooltip-copy {
            font-size: 0.98em;
            background: #323741;
            color: #ffcb4b;
            padding: 4px 12px;
            border-radius: 6px;
            margin-left: 8px;
            position: absolute;
            z-index: 10;
        }
        #serial_number_val {
            color: #EDEDED !important;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        /* Responsive sidebar offcanvas */
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
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        @media (max-width: 767px) {
            .card { padding: 0.7em 0.4em; }
            h2 { font-size: 1.1em; }
            label { font-size: 1em; }
        }
    </style>
    <script>
    function toggleCharge() {
        document.getElementById('costRow').style.display =
            document.getElementById('chargeStudent').checked ? '' : 'none';
    }

    function copySerial() {
        const serial = document.getElementById('serial_number_val').innerText;
        navigator.clipboard.writeText(serial).then(function() {
            showTooltip();
        });
    }

    function showTooltip() {
        let tooltip = document.getElementById('copy-tooltip');
        tooltip.style.display = 'inline';
        setTimeout(() => { tooltip.style.display = 'none'; }, 1100);
    }

    // -- AJAX for student workorder count
    function updateWorkorderCount(studentID) {
        if (!studentID) {
            $('#workorder_count_banner').html('');
            return;
        }
        $.getJSON('workorder_count.php', { studentID: studentID }, function(resp) {
            if(resp && resp.count !== undefined && resp.year) {
                let msg = `This student has had <b>${resp.count}</b> workorder(s) since August 1, ${resp.year}.`;
                if (resp.count > 1) {
                    msg += ' <span style="color:#a85b00;font-weight:bold;">Additional repairs may not be covered by insurance.</span>';
                }
                $('#workorder_count_banner').html(
                    `<div style="background:#ffecb3;color:#a85b00;font-weight:bold;border-left:7px solid #ffc107; border-radius:7px; padding:9px 18px; margin-top:4px;">${msg}</div>`
                );
            } else {
                $('#workorder_count_banner').html('');
            }
        });
    }

    $(function(){
        toggleCharge(); // Set initial state on page load

        $('#studentID').on('change blur', function(){
            updateWorkorderCount($(this).val());
        });

        // Also check at load if studentID field is prefilled
        if($('#studentID').val()) {
            updateWorkorderCount($('#studentID').val());
        }
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
        <div class="card p-4">
            <h2>Edit Workorder</h2>
            <form method="POST" autocomplete="off">
                <div class="mb-3 row">
                    <div class="col-md-6 col-12 mb-2 mb-md-0">
                        <label>Asset Tag</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wo['assignedID']) ?>" readonly>
                    </div>
                    <div class="col-md-6 col-12 d-flex align-items-center">
                        <label style="flex-shrink:0; margin-right:8px;">Serial Number</label>
                        <span id="serial_number_val"><?= htmlspecialchars($serial_number) ?></span>
                        <button type="button" class="copy-btn ms-1" title="Copy Serial" onclick="copySerial()">
                            <i class="fa fa-copy"></i>
                        </button>
                        <span class="tooltip-copy" id="copy-tooltip" style="display:none;">Copied!</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Student ID</label>
                    <input type="text" name="studentID" id="studentID" class="form-control" value="<?= htmlspecialchars($wo['studentID']) ?>">
                </div>
                <div class="mb-3">
                    <label>Student Name</label>
                    <input type="text" name="studentName" class="form-control" value="<?= htmlspecialchars($wo['studentName']) ?>">
                </div>
                <div id="workorder_count_banner"></div>
                <div class="mb-3">
                    <label for="damage">Damage Type</label>
                    <select name="damage" id="damage" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php
                        $types = ["Defective","Accidental","Intentional","Missing","Vandalism"];
                        foreach ($types as $type) {
                            $sel = ($wo['damage'] == $type) ? 'selected' : '';
                            echo "<option value=\"$type\" $sel>$type</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="damageArea">Damage Area</label>
                    <select name="damageArea" id="damageArea" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php
                        $areas = ["LCD","Keyboard","Case/Body","Software/No Boot","Power Cord","Other"];
                        foreach ($areas as $area) {
                            $sel = ($wo['damageArea'] == $area) ? 'selected' : '';
                            echo "<option value=\"$area\" $sel>$area</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Issue / Description</label>
                    <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($wo['description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label>Notes / Staff Comments</label>
                    <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($wo['notes']) ?></textarea>
                </div>
                <!-- RESOLUTION SECTION -->
                <div class="mb-3">
                    <label>Resolution / Outcome</label>
                    <textarea name="resolution" class="form-control" rows="2"><?= htmlspecialchars($wo['resolution'] ?? '') ?></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="warrantyRepair" class="form-check-input" id="warrantyRepair"
                        <?= (isset($wo['warrantyRepair']) && $wo['warrantyRepair']=='Yes') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="warrantyRepair">Warranty Repair</label>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="chargeStudent" class="form-check-input" id="chargeStudent"
                        <?= (isset($wo['chargeStudent']) && $wo['chargeStudent']=='Yes') ? 'checked' : '' ?> onclick="toggleCharge()">
                    <label class="form-check-label" for="chargeStudent">Student Charged</label>
                </div>
                <div class="mb-3" id="costRow" style="<?= (isset($wo['chargeStudent']) && $wo['chargeStudent']=='Yes') ? '' : 'display:none;' ?>">
                    <label>Amount Charged</label>
                    <input type="number" step="0.01" name="Cost" class="form-control" value="<?= htmlspecialchars($wo['Cost'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <?php
                        $statuses = ['new','in_progress','shipped','completed'];
                        foreach($statuses as $status) {
                            $sel = ($wo['status'] === $status) ? 'selected' : '';
                            echo "<option value=\"$status\" $sel>".ucwords(str_replace('_',' ',$status))."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3 d-flex flex-wrap gap-2">
                    <button class="btn btn-warning" type="submit" name="save" value="1"><i class="fa fa-save"></i> Save</button>
                    <button class="btn btn-primary" type="submit" name="inprogress" value="1"><i class="fa fa-tools"></i> Receive/In Progress</button>
                    <button class="btn btn-info text-dark" type="submit" name="ship" value="1"><i class="fa fa-shipping-fast"></i> Ship for Repair</button>
                    <button class="btn btn-success" type="submit" name="close" value="1"><i class="fa fa-lock"></i> Close Ticket</button>
                    <a href="workorders.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
