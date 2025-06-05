<?php
include('/var/cbinfo_connect.php');

// Fetch school name from app_settings
$school_name = "Your School Name"; // fallback default
$q_school = mysqli_query($dbc, "SELECT setting_value FROM app_settings WHERE setting_key='school_name' LIMIT 1");
if ($row_school = mysqli_fetch_assoc($q_school)) {
    $school_name = $row_school['setting_value'];
}

$msg = '';
$step = 1;
$device = null;
$student = null;
$asset_tag = '';
$did_action = false;
$last_workorder_id = null;

// Step 1: Enter/scan Asset Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_tag']) && !isset($_POST['step2'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    if ($device = mysqli_fetch_assoc($device_res)) {
        $step = 2;
    } else {
        $msg = "Device not found. Please check your asset tag.";
    }
}

// Step 2: Enter Lunch PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2']) && isset($_POST['asset_tag']) && isset($_POST['pin'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $pin = trim($_POST['pin']);
    $device_q = "SELECT * FROM CBLocal WHERE asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."' LIMIT 1";
    $device_res = mysqli_query($dbc, $device_q);
    $device = mysqli_fetch_assoc($device_res);

    $q = "SELECT * FROM CATstudent WHERE lunchPin = '".mysqli_real_escape_string($dbc,$pin)."' LIMIT 1";
    $res = mysqli_query($dbc, $q);
    if ($student = mysqli_fetch_assoc($res)) {
        $step = 3;
    } else {
        $msg = "PIN not found. Please try again.";
        $step = 2;
    }
}

// Step 3: Submit Workorder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_workorder'])) {
    $asset_tag = trim($_POST['asset_tag']);
    $studentID = trim($_POST['studentID']);
    $studentName = trim($_POST['studentName']);
    $damageArea = isset($_POST['damageArea']) ? mysqli_real_escape_string($dbc, trim($_POST['damageArea'])) : '';
    $description = isset($_POST['wo_how']) ? mysqli_real_escape_string($dbc, trim($_POST['wo_how'])) : '';
    $notes = isset($_POST['wo_comment']) ? mysqli_real_escape_string($dbc, trim($_POST['wo_comment'])) : '';

    // Insert workorder event (staff can edit "damage" later if needed)
    mysqli_query($dbc, "INSERT INTO CBEvents (
        Event, assignedID, studentID, studentName, status, time, damageArea, description, notes
    ) VALUES (
        'Workorder',
        '$asset_tag',
        '$studentID',
        '$studentName',
        'new',
        NOW(),
        '$damageArea',
        '$description',
        '$notes'
    )");

    // Fetch just created workorder for slip/barcode
    $wo_query = mysqli_query($dbc, "SELECT id FROM CBEvents WHERE Event='Workorder' AND assignedID='".mysqli_real_escape_string($dbc,$asset_tag)."' AND studentID='".mysqli_real_escape_string($dbc,$studentID)."' ORDER BY time DESC LIMIT 1");
    if ($wo = mysqli_fetch_assoc($wo_query)) {
        $last_workorder_id = $wo['id'];
    }

    $msg = "Workorder submitted! Place this slip with your Chromebook and leave it in the designated spot.";
    $did_action = true;
    $step = 4;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Chromebook Workorder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- JsBarcode for barcode printing -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background: #181A20; color: #fff; }
        .kiosk-form { max-width: 500px; margin: 6vh auto 0; background: #23272F; border-radius: 24px; box-shadow: 0 4px 32px #0008; padding: 2em 2em 1em; }
        .kiosk-title { font-size: 2.3rem; color: #ffcb4b; font-weight: bold; text-align: center; margin-bottom: 1em; }
        .big-input { font-size: 2.2rem; text-align: center; height: 64px; }
        .form-select.big-input { font-size: 1.3rem; height: 3.2em; }
        .btn-kiosk { font-size: 2rem; padding: 0.6em 2.2em; border-radius: 1em; font-weight: bold; }
        .btn-kiosk-submit { background: #ffcb4b; color: #181A20; }
        .alert { font-size: 1.2rem; text-align: center; }
        .kiosk-label { font-size: 1.3rem; color: #ffcb4b; margin-bottom: 0.3em; }
        #print-slip { background: #fff; color: #23272F; }
        @media print {
            body * { display: none !important; }
            #print-slip, #print-slip * { display: block !important; visibility: visible !important; }
            #print-slip {
                position: absolute; left: 0; top: 0; width: 100%;
                margin: 0; padding: 0;
                background: #fff !important;
                color: #000 !important;
                border: none !important;
                box-shadow: none !important;
                font-size: 13px !important;
            }
            html, body { width: 80mm !important; }
        }
        #print-slip { font-family: 'Arial', monospace; width: 80mm; margin: auto; background: #fff; color: #23272F; }
        #print-slip table { width: 100%; font-size: 1em; }
        #print-slip td { padding: 2px 0; }
        #print-slip .barcode { text-align: center; margin: 10px 0; }
    </style>
</head>
<body>
<div class="kiosk-form">
    <div class="kiosk-title">
        <div style="font-size:1.3em; font-weight: bold;"><?= htmlspecialchars($school_name) ?></div>
        <div style="font-size:1.08em;">Chromebook Workorder</div>
    </div>
    <?php if($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if($step == 1): ?>
        <!-- Step 1: Enter Asset Tag -->
        <form method="post" autocomplete="off">
            <label class="kiosk-label" for="asset_tag">Scan or Enter Asset Tag</label>
            <input type="text" maxlength="32" name="asset_tag" id="asset_tag" class="form-control big-input mb-3" autofocus required>
            <button class="btn btn-kiosk btn-kiosk-submit w-100 mb-2" type="submit">Next <i class="fa fa-arrow-right"></i></button>
        </form>
    <?php elseif($step == 2 && $device): ?>
        <!-- Step 2: Enter PIN -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
            <label class="kiosk-label" for="pin">Enter Your Lunch PIN</label>
            <input type="password" maxlength="8" name="pin" id="pin" class="form-control big-input mb-3" autofocus required pattern="\d*">
            <button class="btn btn-kiosk btn-kiosk-submit w-100 mb-2" type="submit" name="step2" value="1">Next <i class="fa fa-arrow-right"></i></button>
        </form>
    <?php elseif($step == 3 && $device && $student): ?>
        <!-- Step 3: Workorder form -->
        <form method="post" autocomplete="off" id="workorder_form">
            <input type="hidden" name="studentID" value="<?= htmlspecialchars($student['studentID']) ?>">
            <input type="hidden" name="studentName" value="<?= htmlspecialchars(trim($student['firstName'].' '.$student['lastName'])) ?>">
            <input type="hidden" name="asset_tag" value="<?= htmlspecialchars($device['asset_tag']) ?>">
            <div class="mb-3">
                <label class="kiosk-label" for="damageArea">What part is broken?</label>
                <select name="damageArea" id="damageArea" class="form-select big-input" required>
                    <option value="">-- Select --</option>
                    <option value="LCD">Screen / LCD</option>
                    <option value="Keyboard">Keyboard</option>
                    <option value="Touchpad">Touchpad</option>
                    <option value="Charger">Charger</option>
                    <option value="Case/Body">Case/Body</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="kiosk-label" for="wo_how">Describe the problem (how did it happen?):</label>
                <textarea name="wo_how" id="wo_how" class="form-control big-input" rows="2" required></textarea>
            </div>
            <div class="mb-3">
                <label class="kiosk-label" for="wo_comment">Anything else we should know? (optional)</label>
                <textarea name="wo_comment" id="wo_comment" class="form-control big-input" rows="2"></textarea>
            </div>
            <button class="btn btn-kiosk btn-kiosk-submit w-100 mb-2" type="submit" name="submit_workorder" value="1">
                <i class="fa fa-paper-plane"></i> Submit Workorder
            </button>
            <div class="text-muted mt-2" style="font-size:1.1rem;text-align:center;">
                Asset Tag: <b><?= htmlspecialchars($device['asset_tag']) ?></b><br>
                Student: <b><?= htmlspecialchars(trim($student['firstName'].' '.$student['lastName'])) ?></b>
            </div>
        </form>
    <?php elseif($step == 4): ?>
        <!-- Step 4: Success & Print Slip (button only, slip is OUTSIDE form for print reliability) -->
        <div class="text-center mt-4">
            <div class="mb-3" style="font-size:3em;color:#36d99b;"><i class="fa fa-check-circle"></i></div>
            <div style="font-size:2rem;">Workorder Submitted!</div>
            <div class="mt-3" style="font-size:1.1rem;color:#ffcb4b;">
                Place this slip with your Chromebook and leave it in the designated spot.
            </div>
            <button id="printBtn" class="btn btn-kiosk btn-kiosk-submit w-100 mt-4">
                <i class="fa fa-print"></i> Print Slip
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if($step == 4 && $last_workorder_id): ?>
<!-- Print Slip Block (80mm width, explicit white background) -->
<div id="print-slip" style="width:80mm;min-height:150mm;margin:auto;background:#fff !important;color:#23272F !important;border:2px dashed #444;border-radius:7px;padding:18px 6px 22px 6px;box-sizing:border-box;">
    <div style="text-align:center;">
        <div style="font-size:1.35em;font-weight:bold;letter-spacing:1px;"><?= htmlspecialchars($school_name) ?></div>
        <div style="margin:8px 0 2px 0;">
            <i class="fa-solid fa-screwdriver-wrench" style="font-size:2.2em;color:#3a80d9;"></i>
        </div>
        <div style="font-size:1.15em;font-weight:600;margin-bottom:7px;">
            Technology Repair Ticket
        </div>
        <hr style="border-top:1px solid #ccc;">
    </div>
    <table style="width:99%;font-size:1.08em;margin-top:7px;">
        <tr>
            <td><strong>Date:</strong></td>
            <td>
                <span style="border:1.5px solid #bbb;padding:2px 8px 2px 8px;border-radius:5px;"><?= date('Y-m-d H:i') ?></span>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;"><strong>Student:</strong></td>
            <td style="font-weight:600;"><?= htmlspecialchars(trim($student['firstName'].' '.$student['lastName'])) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top;"><strong>Asset Tag:</strong></td>
            <td style="font-size:1.15em;font-weight:bold;letter-spacing:0.5px;"><?= htmlspecialchars($asset_tag) ?></td>
        </tr>
        <?php
        $wo = null;
        if ($last_workorder_id) {
            $wo_query = mysqli_query($dbc, "SELECT * FROM CBEvents WHERE id='".intval($last_workorder_id)."' LIMIT 1");
            $wo = mysqli_fetch_assoc($wo_query);
        }
        ?>
        <tr>
            <td><strong>Part:</strong></td>
            <td><?= htmlspecialchars($wo['damageArea'] ?? '') ?></td>
        </tr>
        <tr>
            <td><strong>Description:</strong></td>
            <td><?= htmlspecialchars($wo['description'] ?? '') ?></td>
        </tr>
        <?php if (!empty($wo['notes'])): ?>
        <tr>
            <td><strong>Comment:</strong></td>
            <td><?= htmlspecialchars($wo['notes']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
    <div style="text-align:center;margin:15px 0 0 0;">
        <svg id="wo_barcode" style="width:95%;max-width:180px;height:44px;display:block;margin:0 auto;"></svg>
        <div style="font-size:10px;text-align:center;margin-top:-5px;">
            Scan for details:<br><?= htmlspecialchars("workorder_receipt.php?id=" . $last_workorder_id) ?>
        </div>
    </div>
    <div style="border-top:1.5px dashed #aaa;margin:20px 0 7px 0;"></div>
    <div style="font-size:1.1em;text-align:center;padding:7px 0 0 0;">
        <b>Instructions:</b> Put this slip inside your Chromebook and place it in the designated area.<br>
        Thank you for helping us keep our tech working!
    </div>
</div>
<script>
    JsBarcode("#wo_barcode", "<?= htmlspecialchars("workorder_receipt.php?id=" . $last_workorder_id) ?>", {
        format: "CODE128",
        width: 0.8,      // Reduced width for 80mm paper
        height: 44,
        displayValue: false,
        margin: 0
    });

    document.getElementById('printBtn').onclick = function() {
        window.print();
        setTimeout(function(){
            window.location = "student_kiosk.php";
        }, 1000);
    };
</script>
<?php endif; ?>
</body>
</html>
