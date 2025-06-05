<?php
include('session_check.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('/var/cbinfo_connect.php');

// --- Handle form submission ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignedID = mysqli_real_escape_string($dbc, trim($_POST['assignedID']));
    $serialNumber = mysqli_real_escape_string($dbc, trim($_POST['serial_number']));
    $model = mysqli_real_escape_string($dbc, trim($_POST['model']));
    $studentID = mysqli_real_escape_string($dbc, trim($_POST['studentID']));
    $studentName = mysqli_real_escape_string($dbc, trim($_POST['studentName']));
    $damage = mysqli_real_escape_string($dbc, trim($_POST['damage']));
    $damageArea = mysqli_real_escape_string($dbc, trim($_POST['damageArea']));
    $description = mysqli_real_escape_string($dbc, trim($_POST['description']));
    $notes = mysqli_real_escape_string($dbc, trim($_POST['notes']));
    $status = 'new';

    $q = "INSERT INTO CBEvents
        (Event, assignedID, serialNumber, model, studentID, studentName, damage, damageArea, description, status, notes, time)
        VALUES
        ('Workorder', '$assignedID', '$serialNumber', '$model', '$studentID', '$studentName', '$damage', '$damageArea', '$description', '$status', '$notes', NOW())";
    if (mysqli_query($dbc, $q)) {
        header("Location: workorders.php?msg=created");
        exit;
    } else {
        $msg = "Failed to create workorder. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Workorder | Chromebook System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        body { background: #181A20; color: #fff; }
        .container { max-width: 700px; margin-top: 2em; }
        .card { background: #23272F; border: none; }
        .form-control, .form-select { background: #23272F; color: #fff; border: 1px solid #444; }
        .form-control:focus, .form-select:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
        label { color: #ffcb4b; }
        h1, h2, h3, h4, h5, h6 { color: #ffcb4b !important; }
        .btn-warning { color: #181A20; font-weight: bold; }
        a.btn-secondary { color: #fff; }
        .insurance-warning { background: #ffecb3; color: #a85b00; font-weight: bold; border-left: 7px solid #ffc107; padding: 10px 18px; border-radius:7px; margin-bottom:1em; }
        /* --- Dark mode fix for jQuery UI Autocomplete --- */
        .ui-autocomplete {
            background: #23272F !important;
            color: #fff !important;
            border: 1px solid #444 !important;
        }
        .ui-menu-item-wrapper,
        .ui-menu-item {
            background: #23272F !important;
            color: #fff !important;
        }
        .ui-menu-item-wrapper.ui-state-active,
        .ui-menu-item.ui-state-active {
            background: #ffcb4b !important;
            color: #181A20 !important;
            font-weight: bold;
        }
    </style>
    <script>
    // Device info lookup
    function updateInsuranceWarning(studentID) {
        if (!studentID) {
            $('#insurance_banner').html('');
            return;
        }
        $.getJSON('search_student.php', { check_workorders: 1, student_id: studentID }, function(resp){
            if(resp && resp.count !== undefined) {
                if (resp.count > 1) {
                    $('#insurance_banner').html('<div class="insurance-warning">Warning: This student has had <b>' + resp.count + '</b> workorder(s) since last August 1. Additional repairs may not be covered by insurance.</div>');
                } else {
                    $('#insurance_banner').html('');
                }
            }
        });
    }

    $(function(){
        // Lookup device info by asset tag
        $('#assignedID').on('blur change', function(){
            var asset_tag = $(this).val();
            if (asset_tag) {
                $.getJSON('get_device_info.php', { asset_tag: asset_tag }, function(data){
                    if(data && data.success) {
                        $('#serial_number').val(data.serial_number);
                        $('#model').val(data.model);
                        $('#studentID').val(data.student_id).trigger('change');
                        $('#studentName').val(data.student_name);
                    } else {
                        $('#serial_number, #model, #studentID, #studentName').val('');
                        $('#insurance_banner').html('');
                    }
                });
            } else {
                $('#serial_number, #model, #studentID, #studentName').val('');
                $('#insurance_banner').html('');
            }
        });

        // Student name autocomplete - correct for new search_student.php
        $("#studentName").autocomplete({
            minLength: 2,
            source: function(request, response) {
                $.getJSON("search_student.php", { q: request.term }, function(data) {
                    response(data); // data has {label, value, id}
                });
            },
            select: function(event, ui) {
                $('#studentID').val(ui.item.id).trigger('change');
                $('#studentName').val(ui.item.value); // Fill in just the name
            }
        });

        // Insurance warning check when studentID changes (manually or by autofill)
        $('#studentID').on('change blur', function(){
            var studentID = $(this).val();
            updateInsuranceWarning(studentID);
        });

        // Initial check if value already present
        if($('#studentID').val()) {
            updateInsuranceWarning($('#studentID').val());
        }
    });
    </script>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h2>Create New Workorder</h2>
        <?php if($msg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="assignedID">Device Asset Tag</label>
                <input type="text" name="assignedID" id="assignedID" class="form-control" required autofocus placeholder="Scan or enter asset tag">
            </div>
            <div class="mb-3">
                <label for="serial_number">Serial Number</label>
                <input type="text" name="serial_number" id="serial_number" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label for="model">Model</label>
                <input type="text" name="model" id="model" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label for="studentID">Student ID <span class="text-secondary">(auto-filled if checked out, can override)</span></label>
                <input type="text" name="studentID" id="studentID" class="form-control">
            </div>
            <div class="mb-3">
                <label for="studentName">Student Name (type to search)</label>
                <input type="text" name="studentName" id="studentName" class="form-control">
            </div>
            <div id="insurance_banner"></div>
            <div class="mb-3">
                <label for="damage">Damage Type</label>
                <select name="damage" id="damage" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="Defective">Defective</option>
                    <option value="Accidental">Accidental</option>
                    <option value="Intentional">Intentional</option>
                    <option value="Missing">Missing</option>
                    <option value="Vandalism">Vandalism</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="damageArea">Damage Area</label>
                <select name="damageArea" id="damageArea" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="LCD">LCD</option>
                    <option value="Keyboard">Keyboard</option>
                    <option value="Case/Body">Case/Body</option>
                    <option value="Software/No Boot">Software/Loads/No Boot</option>
                    <option value="Power Cord">Power Cord</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="description">Issue / Description</label>
                <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
            </div>
            <div class="mb-3">
                <label for="notes">Notes / Staff Comments (optional)</label>
                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <button class="btn btn-warning" type="submit"><i class="fa fa-save"></i> Create Workorder</button>
                <a href="workorders.php" class="btn btn-secondary ms-2"><i class="fa fa-arrow-left"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
