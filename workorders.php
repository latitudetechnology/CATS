<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// --- UTC to Central Time Helper ---
function utc_to_central($ts) {
    if (!$ts) return '';
    $dt = new DateTime($ts, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('America/Chicago'));
    return $dt->format('Y-m-d g:i A') . ' CT';
}

// --- Filter/Search ---
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = [];
if ($status !== '') $where[] = "status='".mysqli_real_escape_string($dbc,$status)."'";
if ($search) {
    $safe_search = mysqli_real_escape_string($dbc, $search);
    $where[] = "(assignedID LIKE '%$safe_search%' OR studentID LIKE '%$safe_search%' OR studentName LIKE '%$safe_search%' OR description LIKE '%$safe_search%')";
}
$where_sql = $where ? "AND ".implode(" AND ", $where) : "";

// Custom ORDER BY for status ordering
$order_status = "FIELD(status, 'new','in_progress','shipped','completed'), time DESC";

// Find last August 1st for school-year workorder counting
$today = new DateTime();
$aug1 = new DateTime($today->format('Y') . '-08-01');
if ($today < $aug1) { // If before this year's Aug 1, use last year's
    $aug1->modify('-1 year');
}
$aug1_str = $aug1->format('Y-m-d');

// We'll cache counts by studentID for efficiency
$student_workorder_counts = [];

// Load all "new" workorders (will show the count for these)
if ($status === 'new' || !$status) {
    $wo_for_counts_q = "SELECT studentID FROM CBEvents WHERE Event='Workorder' AND status='new' AND time >= '$aug1_str'";
    $wo_for_counts = mysqli_query($dbc, $wo_for_counts_q);
    $studentIDs = [];
    while ($row = mysqli_fetch_assoc($wo_for_counts)) {
        if (!empty($row['studentID'])) {
            $studentIDs[] = $row['studentID'];
        }
    }
    if ($studentIDs) {
        $ids_escaped = array_map(function($id) use ($dbc) {
            return "'" . mysqli_real_escape_string($dbc, $id) . "'";
        }, $studentIDs);
        $ids_list = implode(',', array_unique($ids_escaped));
        // Get counts for all these students
        $counts_q = "SELECT studentID, COUNT(*) as wocount FROM CBEvents WHERE Event='Workorder' AND time >= '$aug1_str' AND studentID IN ($ids_list) GROUP BY studentID";
        $counts_res = mysqli_query($dbc, $counts_q);
        while ($row = mysqli_fetch_assoc($counts_res)) {
            $student_workorder_counts[$row['studentID']] = $row['wocount'];
        }
    }
}

$workorders_q = "SELECT * FROM CBEvents WHERE Event='Workorder' $where_sql ORDER BY $order_status LIMIT 100";
$workorders = mysqli_query($dbc, $workorders_q);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Work Orders | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .card {
            background: #23272F;
            border: none;
            color: #F4F4F8;
            box-shadow: 0 4px 18px #10121a22;
        }
        h3, h4, .card-title { color: #ffcb4b !important; }
        .table thead th { background: #23272F; color: #ffcb4b !important; font-weight: 700; font-size: 1.1em;}
        .table tbody tr { color: #fff; font-size: 1.05em; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #252834 !important; }
        .form-control, .form-select { background: #23272F; color: #fff; border: 1px solid #444; font-size:1.08em; }
        .form-control:focus, .form-select:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
        .btn, .form-control, .form-select { font-size: 1.09em; }
        .pagination .page-link { background: #23272F; color: #fff; border: 1px solid #444; }
        .pagination .page-item.active .page-link { background: #ffcb4b; color: #181A20; border: 1px solid #ffcb4b; }
        /* SIDEBAR FIX for desktop/mobile */
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
            .card-title { font-size:1.15em; }
        }
    </style>
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
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">
                    Work Orders
                    <a href="workorder_create.php" class="btn btn-warning btn-sm ms-3"><i class="fa fa-plus"></i> New Work Order</a>
                </h4>
                <!-- Filter/Search -->
                <form class="mb-3 row g-2 align-items-center" method="get" action="workorders.php">
                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php
                            $statuses = ['new','in_progress','shipped','completed'];
                            foreach($statuses as $stat) {
                                $sel = ($status === $stat) ? "selected" : "";
                                echo "<option value='$stat' $sel>".ucwords(str_replace('_',' ',$stat))."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 mb-2 mb-md-0">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by asset tag, student, or issue...">
                    </div>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <button class="btn btn-warning w-100" type="submit"><i class="fa fa-search"></i> Search</button>
                    </div>
                    <?php if($search || $status): ?>
                    <div class="col-12 col-md-1 mb-2 mb-md-0">
                        <a href="workorders.php" class="btn btn-secondary w-100"><i class="fa fa-times"></i> Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Asset Tag</th>
                                <th>Student</th>
                                <th>Issue/Description</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($workorders)>0): ?>
                                <?php while($row = mysqli_fetch_assoc($workorders)) {
                                    // For new workorders, show count since Aug 1
                                    $student_name = htmlspecialchars($row['studentName']);
                                    $count_str = '';
                                    if ($row['status'] === 'new' && !empty($row['studentID'])) {
                                        $sid = $row['studentID'];
                                        $wocount = isset($student_workorder_counts[$sid]) ? $student_workorder_counts[$sid] : null;
                                        if ($wocount !== null) {
                                            $count_str = " <span style='color:#ffcb4b;' title='Workorders this school year'>($wocount this year)</span>";
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars(utc_to_central($row['time'])) ?></td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$row['status']))) ?></td>
                                        <td><?= htmlspecialchars($row['assignedID']) ?></td>
                                        <td><?= $student_name . $count_str ?></td>
                                        <td><?= htmlspecialchars($row['description']) ?></td>
                                        <td>
                                            <a href="workorder_edit.php?id=<?= urlencode($row['id']) ?>" class="btn btn-sm btn-secondary"><i class="fa fa-edit"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">No workorders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
