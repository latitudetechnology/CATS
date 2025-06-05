<?php
include('/var/cbinfo_connect.php');
header('Content-Type: application/json');

if (isset($_GET['check_workorders']) && isset($_GET['student_id'])) {
    // Return count of workorders since Aug 1 for this student
    $student_id = mysqli_real_escape_string($dbc, $_GET['student_id']);
    $since = date('Y-m-d', strtotime('August 1'));
    $sql = "SELECT COUNT(*) AS count FROM CBEvents WHERE studentID='$student_id' AND time >= '$since'";
    $res = mysqli_query($dbc, $sql);
    $row = mysqli_fetch_assoc($res);
    echo json_encode(['count' => (int)$row['count']]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
if ($q !== '') {
    $q_safe = mysqli_real_escape_string($dbc, $q);
    $sql = "SELECT studentID, firstName, lastName, prefName, Grade FROM CATstudent
            WHERE lastName LIKE '%$q_safe%' OR firstName LIKE '%$q_safe%' OR prefName LIKE '%$q_safe%' ORDER BY lastName, firstName LIMIT 15";
    $res = mysqli_query($dbc, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $full = $row['firstName'] . ' ' . $row['lastName'];
        if ($row['prefName']) $full .= " (" . $row['prefName'] . ")";
        $results[] = [
            'label' => $full . ' (' . $row['studentID'] . (isset($row['Grade']) ? ', ' . $row['Grade'] : '') . ')',
            'value' => $full,
            'id'    => $row['studentID']
        ];
    }
}
if ($results) {
    echo json_encode($results);
}
