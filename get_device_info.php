<?php
include('/var/cbinfo_connect.php');
header('Content-Type: application/json');

$asset_tag = isset($_GET['asset_tag']) ? trim($_GET['asset_tag']) : '';
if (!$asset_tag) {
    echo json_encode(['success' => false]);
    exit;
}

$q = "SELECT c.asset_tag, c.serial_number, c.model, c.is_checked_out, c.student_id, s.firstName, s.lastName, s.prefName
      FROM CBLocal c
      LEFT JOIN CATstudent s ON c.student_id = s.studentID
      WHERE c.asset_tag='".mysqli_real_escape_string($dbc,$asset_tag)."'";
$res = mysqli_query($dbc, $q);
if ($row = mysqli_fetch_assoc($res)) {
    // Build the full student name
    $student_name = '';
    if ($row['firstName'] || $row['lastName']) {
        $student_name = trim($row['firstName'] . ' ' . $row['lastName']);
        if ($row['prefName']) {
            $student_name .= ' (' . $row['prefName'] . ')';
        }
    }
    echo json_encode([
        'success' => true,
        'serial_number' => $row['serial_number'],
        'model' => $row['model'],
        'student_id' => $row['student_id'],
        'student_name' => $student_name,
        'is_checked_out' => $row['is_checked_out'],
    ]);
} else {
    echo json_encode(['success' => false]);
}
