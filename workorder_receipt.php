<?php
include('/var/cbinfo_connect.php');

// Fetch school name from app_settings
$school_name = "Your School Name"; // fallback default
$q_school = mysqli_query($dbc, "SELECT setting_value FROM app_settings WHERE setting_key='school_name' LIMIT 1");
if ($row_school = mysqli_fetch_assoc($q_school)) {
    $school_name = $row_school['setting_value'];
}

// Validate and get workorder ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$wo = null;
if ($id) {
    $q = "SELECT * FROM CBEvents WHERE id='$id' AND Event='Workorder' LIMIT 1";
    $res = mysqli_query($dbc, $q);
    $wo = mysqli_fetch_assoc($res);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Repair Ticket #<?= $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fa; color: #222; font-family: 'Arial', monospace; }
        .ticket-box {
            width: 80mm; min-height: 100mm; margin: 20px auto;
            background: #fff; color: #23272F;
            border: 2px dashed #444; border-radius: 7px;
            padding: 18px 6px 22px 6px; box-sizing: border-box;
        }
        .ticket-header { text-align: center; }
        .ticket-title { font-size: 1.25em; font-weight: bold; letter-spacing: 1px; }
        .ticket-icon { margin: 8px 0 2px 0; font-size: 2.2em; color: #3a80d9; }
        .ticket-label { font-weight: bold; }
        .ticket-field { margin-bottom: 8px; }
        .ticket-table { width: 99%; font-size: 1.08em; margin-top: 10px; }
        .ticket-table td { padding: 2px 0; vertical-align: top; }
        .ticket-instructions {
            margin-top: 18px;
            border-top: 1.5px dashed #aaa;
            padding-top: 7px;
            font-size: 1.07em;
            text-align: center;
        }
        @media print {
            body * { display: none !important; }
            .ticket-box, .ticket-box * { display: block !important; visibility: visible !important; }
            .ticket-box { position: absolute; left: 0; top: 0; width: 100%; background: #fff !important; color: #000 !important; border: none !important; }
            html, body { width: 80mm !important; }
        }
    </style>
</head>
<body>
<?php if(!$wo): ?>
    <div class="ticket-box">
        <div class="ticket-header">
       	    <div class="ticket-title"><?= htmlspecialchars($school_name) ?></div>
            <div class="ticket-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <div style="margin:10px 0 6px 0;font-weight:bold;font-size:1.1em;">Workorder Not Found</div>
            <div style="color:#a00;font-size:1.05em;">This repair ticket ID was not found. Please check the barcode or contact tech support.</div>
        </div>
    </div>
<?php else: ?>
    <div class="ticket-box">
        <div class="ticket-header">
	    <div class="ticket-title"><?= htmlspecialchars($school_name) ?></div>
            <div class="ticket-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <div style="font-size:1.08em;font-weight:600;">Technology Repair Ticket</div>
            <hr style="border-top:1px solid #ccc;">
        </div>
        <table class="ticket-table">
            <tr>
                <td class="ticket-label">Ticket #</td>
                <td><?= $wo['id'] ?></td>
            </tr>
            <tr>
                <td class="ticket-label">Date</td>
                <td>
                    <span style="border:1.5px solid #bbb;padding:2px 8px 2px 8px;border-radius:5px;"><?= date('Y-m-d H:i', strtotime($wo['time'])) ?></span>
                </td>
            </tr>
            <tr>
                <td class="ticket-label">Student</td>
                <td><?= htmlspecialchars($wo['studentName']) ?></td>
            </tr>
            <tr>
                <td class="ticket-label">Asset Tag</td>
                <td style="font-size:1.13em;font-weight:bold;letter-spacing:0.5px;"><?= htmlspecialchars($wo['assignedID']) ?></td>
            </tr>
            <tr>
                <td class="ticket-label">Part Broken</td>
                <td><?= htmlspecialchars($wo['wo_part']) ?></td>
            </tr>
            <tr>
                <td class="ticket-label">How</td>
                <td><?= htmlspecialchars($wo['wo_how']) ?></td>
            </tr>
            <?php if (!empty($wo['wo_comment'])): ?>
            <tr>
                <td class="ticket-label">Comment</td>
                <td><?= htmlspecialchars($wo['wo_comment']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="ticket-label">Status</td>
                <td><?= htmlspecialchars(ucwords(str_replace('_',' ', $wo['status']))) ?></td>
            </tr>
        </table>
        <div class="ticket-instructions">
            <b>Instructions:</b> Put this slip inside your Chromebook and place it in the designated area.<br>
            Thank you for helping us keep our tech working!
        </div>
    </div>
<?php endif; ?>
</body>
</html>
