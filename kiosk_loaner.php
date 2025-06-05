<?php
include('/var/cbinfo_connect.php');

$msg = '';
$step = 1;
$device = null;
$student = null;
$asset_tag = '';
$did_action = false;

// Step 1: Enter or scan Asset Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_tag']) && !isset($_POST['step2'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    if ($device = mysqli_fetch_assoc($device_res)) {
        if ($device['is_checked_out']) {
            $step = 2; // Show check-in button
        } else {
            $step = 3; // Prompt for PIN for checkout
        }
    } else {
        $msg = "Device not found. Please check your asset tag.";
    }
}

// Step 2: Process Check In (no PIN required)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_checkin']) && isset($_POST['asset_tag'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);

    if ($device && $device['is_checked_out']) {
        $studentID = $device['student_id'];
        $stu_q = "SELECT * FROM CATstudent WHERE studentID='".mysqli_real_escape_string($dbc,$studentID)."' LIMIT 1";
        $stu_res = mysqli_query($dbc, $stu_q);
        $stu = mysqli_fetch_assoc($stu_res);
        $studentName = $stu ? trim($stu['firstName'].' '.$stu['lastName']) : '';

        mysqli_query($dbc, "UPDATE CBLocal SET is_checked_out=0, student_id='', checked_in_date=NOW() WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."'");
        // Use event name based on loaner status
        $event_name = ($device['is_loaner']) ? 'Loaner Checked In' : 'Check In';
        mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, studentID, studentName, status, time) VALUES ('$event_name', '$asset_tag', '$studentID', '$studentName', 'checked_in', NOW())");
        $msg = "Checked In! Thank you!";
        $did_action = true;
        $step = 1;
    } else {
        $msg = "Device is not checked out.";
        $step = 1;
    }
}

// Step 3: Enter PIN for checkout (only if not checked out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2']) && isset($_POST['asset_tag']) && isset($_POST['pin'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $pin = trim($_POST['pin']);
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);

    // Lookup student by PIN
    $q = "SELECT * FROM CATstudent WHERE lunchPin = '".mysqli_real_escape_string($dbc,$pin)."' LIMIT 1";
    $res = mysqli_query($dbc, $q);
    if ($student = mysqli_fetch_assoc($res)) {
        $step = 4; // Ready to check out
    } else {
        $msg = "PIN not found. Please try again.";
        $step = 3;
    }
}

// Step 4: Process checkout (with loaner reason)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_checkout'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $studentID = trim($_POST['studentID']);
    $studentName = trim($_POST['studentName']);
    $loaner_reason = isset($_POST['loaner_reason']) ? mysqli_real_escape_string($dbc, trim($_POST['loaner_reason'])) : '';
    $loaner_reason_other = isset($_POST['loaner_reason_other']) ? mysqli_real_escape_string($dbc, trim($_POST['loaner_reason_other'])) : '';
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);

    if ($device && !$device['is_checked_out']) {
        mysqli_query($dbc, "UPDATE CBLocal SET is_checked_out=1, student_id='$studentID', checked_out_date=NOW(), checked_in_date=NULL WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."'");
        // Use event name based on loaner status
        $event_name = ($device['is_loaner']) ? 'Loaner Checked Out' : 'Check Out';
        mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, studentID, studentName, status, time, loaner_reason, loaner_reason_other) VALUES ('$event_name', '$asset_tag', '$studentID', '$studentName', 'checked_out', NOW(), '$loaner_reason', '$loaner_reason_other')");
        $msg = "Checked Out! Take care of your device.";
        $did_action = true;
    } else {
        $msg = "Unable to check out. Please try again.";
    }
    $step = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Chromebook Loaner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        .kiosk-form { max-width: 440px; margin: 6vh auto 0; background: #23272F; border-radius: 24px; box-shadow: 0 4px 32px #0008; padding: 2em 2em 1em; }
        .kiosk-title { font-size: 2.3rem; color: #ffcb4b; font-weight: bold; text-align: center; margin-bottom: 1em; }
        .big-input { font-size: 2.2rem; text-align: center; height: 64px; }
        .form-select.big-input { font-size: 1.3rem; height: 3.2em; }
        .btn-kiosk { font-size: 2rem; padding: 0.6em 2.2em; border-radius: 1em; font-weight: bold; }
        .btn-kiosk-checkin { background: #36d99b; color: #181A20; }
        .btn-kiosk-checkout { background: #ffcb4b; color: #181A20; }
        .btn-kiosk-reason {
            background: #292D38;
            color: #fff;
            border: 2px solid #444;
            font-size: 1.3em;
            font-weight: 500;
            margin-bottom: 10px;
            border-radius: 1em;
            transition: 0.09s;
        }
        .btn-kiosk-reason.btn-warning {
            background: #ffcb4b !important;
            color: #181A20 !important;
            border-color: #ffcb4b !important;
        }
        .btn-kiosk-reason:active, .btn-kiosk-reason:focus {
            box-shadow: 0 0 0 2px #ffcb4b77;
            outline: none;
        }
        .alert { font-size: 1.2rem; text-align: center; }
        .kiosk-label { font-size: 1.3rem; color: #ffcb4b; margin-bottom: 0.3em; }
    </style>
    <script>
        <?php if($did_action): ?>
        setTimeout(function(){
            window.location = "student_kiosk.php";
        }, 5000);
        <?php endif; ?>
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Reason selection buttons
        document.querySelectorAll('.btn-kiosk-reason').forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Remove "active" state from all
                document.querySelectorAll('.btn-kiosk-reason').forEach(function(b) {
                    b.classList.remove('btn-warning');
                });
                // Mark this button as active
                btn.classList.add('btn-warning');
                // Set hidden field
                document.getElementById('loaner_reason').value = btn.dataset.value;
                // Show/hide other reason box
                if (btn.dataset.value === "Other") {
                    document.getElementById('otherReasonRow').style.display = "";
                    document.getElementById('loaner_reason_other').focus();
                } else {
                    document.getElementById('otherReasonRow').style.display = "none";
                    document.getElementById('loaner_reason_other').value = "";
                }
            });
        });
        // On submit, validate that a reason is chosen
        var form = document.getElementById('loaner_checkout_form');
        if(form) {
            form.addEventListener('submit', function(e) {
                if(!document.getElementById('loaner_reason').value) {
                    alert('Please select a reason.');
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</head>
<body>
<div class="kiosk-form">
    <div class="kiosk-title">
        Loaner Chromebook Check In / Check Out
    </div>
    <?php if($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if($step == 1): ?>
        <!-- Step 1: Enter Asset Tag -->
        <form method="post" autocomplete="off">
            <label class="kiosk-label" for="asset_tag">Scan or Enter Asset Tag</label>
            <input type="text" maxlength="32" name="asset_tag" id="asset_tag" class="form-control big-input mb-3" autofocus required>
            <button class="btn btn-kiosk btn-kiosk-checkout w-100 mb-2" type="submit">Next <i class="fa fa-arrow-right"></i></button>
        </form>
    <?php elseif($step == 2 && $device): ?>
        <!-- Step 2: Show check-in button, no PIN required -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
            <button class="btn btn-kiosk btn-kiosk-checkin w-100 mb-2" type="submit" name="do_checkin" value="1">
                <i class="fa fa-arrow-down"></i> Check In Device
            </button>
            <div class="text-muted mt-2" style="font-size:1.1rem;text-align:center;">
                Asset Tag: <b><?= htmlspecialchars($device['asset_tag']) ?></b><br>
                Currently checked out to: <b><?= htmlspecialchars($device['student_id']) ?></b>
            </div>
        </form>
    <?php elseif($step == 3 && $device): ?>
        <!-- Step 3: Enter PIN for checkout -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
            <label class="kiosk-label" for="pin">Enter Your Lunch PIN</label>
            <input type="password" maxlength="8" name="pin" id="pin" class="form-control big-input mb-3" autofocus required pattern="\d*">
            <button class="btn btn-kiosk btn-kiosk-checkout w-100 mb-2" type="submit" name="step2" value="1">Next <i class="fa fa-arrow-right"></i></button>
        </form>
    <?php elseif($step == 4 && $device && $student): ?>
        <!-- Step 4: Show check-out button with reason (now with buttons!) -->
        <form method="post" autocomplete="off" id="loaner_checkout_form">
            <input type="hidden" name="studentID" value="<?= htmlspecialchars($student['studentID']) ?>">
            <input type="hidden" name="studentName" value="<?= htmlspecialchars(trim($student['firstName'].' '.$student['lastName'])) ?>">
            <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
            <div class="mb-3">
                <label class="kiosk-label">Why are you borrowing a loaner?</label>
                <div class="d-grid gap-2" id="reason_buttons">
                    <button type="button" class="btn btn-lg btn-kiosk-reason" data-value="Do Not Have My Chromebook">Do Not Have My Chromebook</button>
                    <button type="button" class="btn btn-lg btn-kiosk-reason" data-value="Chromebook Not Charged">Chromebook Not Charged</button>
                    <button type="button" class="btn btn-lg btn-kiosk-reason" data-value="Chromebook Being Repaired">Chromebook Being Repaired</button>
                    <button type="button" class="btn btn-lg btn-kiosk-reason" data-value="Other">Other</button>
                </div>
                <input type="hidden" name="loaner_reason" id="loaner_reason" value="" required>
            </div>
            <div class="mb-3" id="otherReasonRow" style="display:none;">
                <label class="kiosk-label" for="loaner_reason_other">Please specify</label>
                <input type="text" name="loaner_reason_other" id="loaner_reason_other" class="form-control big-input">
            </div>
            <button class="btn btn-kiosk btn-kiosk-checkout w-100 mb-2" type="submit" name="do_checkout" value="1">
                <i class="fa fa-arrow-up"></i> Check Out Device
            </button>
            <div class="text-muted mt-2" style="font-size:1.1rem;text-align:center;">
                Asset Tag: <b><?= htmlspecialchars($device['asset_tag']) ?></b><br>
                Student: <b><?= htmlspecialchars(trim($student['firstName'].' '.$student['lastName'])) ?></b>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
