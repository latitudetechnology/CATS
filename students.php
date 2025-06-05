<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// --- Check if photos enabled ---
$photos_enabled = 0;
$settings_q = mysqli_query($dbc, "SELECT setting_value FROM app_settings WHERE setting_key='student_photos_enabled' LIMIT 1");
if ($settings = mysqli_fetch_assoc($settings_q)) {
    $photos_enabled = (int)$settings['setting_value'];
}
$student_photo_dir = 'student_photos/';
$default_photo = $student_photo_dir . 'photo_not_available.jpg';

// --- Student Search ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search) {
    $safe_search = mysqli_real_escape_string($dbc, $search);
    $where = "WHERE
        studentID LIKE '%$safe_search%' OR
        firstName LIKE '%$safe_search%' OR
        lastName LIKE '%$safe_search%' OR
        prefName LIKE '%$safe_search%' OR
        lunchPin LIKE '%$safe_search%' OR
        Grade LIKE '%$safe_search%'";
}
$limit = 50; // limit for usability
$query = "SELECT * FROM CATstudent $where ORDER BY lastName ASC, firstName ASC LIMIT $limit";
$results = mysqli_query($dbc, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Lookup | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #181A20; color: #fff; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; }
        h3 { color: #ffcb4b; }
        .form-control { background: #23272F; color: #fff; border: 1px solid #444; }
        .form-control:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
        .btn-view { background: #ffcb4b; color: #181A20; font-weight: bold; }
        .btn-view:hover { background: #ffea9a; color: #181A20; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-secondary { color: #fff; }
        .table thead th { background: #23272F; color: #ffcb4b; font-weight: 700;}
        .table-striped tbody tr:nth-of-type(odd) { background-color: #23242e !important; }
        .table tbody tr { color: #fff; }
        .table-responsive { font-size: 1.04em; }
        .alert-warning { background: #4b350c; color: #ffcb4b; border: none;}
        .thumb-photo {
            width: 40px; height: 52px; object-fit: cover;
            border-radius: 6px; border: 1.5px solid #ffcb4b22; box-shadow: 0 1px 4px #181A2015;
            background: #333;
        }
        /* Sidebar nav fixes for active/highlight */
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
            h3 { font-size: 1.12em; }
            .table-responsive { font-size: 0.95em; }
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
                <h3 class="mb-3">Student Lookup</h3>
                <form class="mb-3 row g-2 align-items-center" method="get" action="students.php">
                    <div class="col-12 col-md-7 mb-2 mb-md-0">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name, grade, or lunch PIN...">
                    </div>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <button class="btn btn-warning w-100" type="submit"><i class="fa fa-search"></i> Search</button>
                    </div>
                    <?php if($search): ?>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <a href="students.php" class="btn btn-secondary w-100"><i class="fa fa-times"></i> Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
                <?php if($search && mysqli_num_rows($results) == 0): ?>
                    <div class="alert alert-warning">No students found matching your search.</div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <?php if ($photos_enabled): ?><th></th><?php endif; ?>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Preferred Name</th>
                                <th>Grade</th>
                                <th>Lunch PIN</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($results) > 0): ?>
                                <?php while($stu = mysqli_fetch_assoc($results)) {
                                    // PHOTO THUMB
                                    $thumb = '';
                                    if ($photos_enabled) {
                                        $stu_photo = $student_photo_dir . $stu['studentID'] . '.jpg';
                                        $show_photo = (file_exists(__DIR__ . '/' . $stu_photo)) ? $stu_photo : $default_photo;
                                        $thumb = '<img src="'.htmlspecialchars($show_photo).'?v='.time().'" alt="Photo" class="thumb-photo" />';
                                    }
                                ?>
                                    <tr>
                                        <?php if ($photos_enabled): ?><td><?= $thumb ?></td><?php endif; ?>
                                        <td><?= htmlspecialchars($stu['firstName']) ?></td>
                                        <td><?= htmlspecialchars($stu['lastName']) ?></td>
                                        <td><?= htmlspecialchars($stu['prefName']) ?></td>
                                        <td><?= htmlspecialchars($stu['Grade']) ?></td>
                                        <td><?= htmlspecialchars($stu['lunchPin']) ?></td>
                                        <td>
                                            <a href="student_details.php?student_id=<?= urlencode($stu['studentID']) ?>" class="btn btn-view btn-sm">
                                                <i class="fa fa-user"></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $photos_enabled ? '7' : '6' ?>" class="text-center">Enter a name, grade, or PIN to search for students.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if(mysqli_num_rows($results) >= $limit): ?>
                        <div class="text-warning mt-2">Showing first <?= $limit ?> results. Refine your search for more specific results.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
