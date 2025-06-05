<?php
include('/var/cbinfo_connect.php');
header('Content-Type: application/json');

$studentID = isset($_GET['studentID']) ? mysqli_real_escape_string($dbc, $_GET['studentID']) : '';
if (!$studentID) {
    echo json_encode(['count' => 0, 'year' => '']);
    exit;
}

// Figure out previous/current August 1
$now = new DateTime();
$year = $now->format('Y');
if ($now < new DateTime("$year-08-01")) {
    $year--;
}
$august1 = "$year-08-01";

$q = "SELECT COUNT(*) AS cnt FROM CBEvents WHERE studentID='$studentID' AND Event='Workorder' AND time >= '$august1'";

$res = mysqli_query($dbc, $q);
$count = 0;
if ($row = mysqli_fetch_assoc($res)) {
    $count = (int)$row['cnt'];
}
echo json_encode(['count' => $count, 'year' => $year]);
