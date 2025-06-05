<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// --- Search ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search) {
    $safe_search = mysqli_real_escape_string($dbc, $search);
    // Assuming student names are in CATstudent table, the search needs to be adjusted
    // For simplicity, current search for student_id will find devices assigned to a student if their ID is searched.
    // A more complex search would involve a JOIN if searching by student name directly in this query.
    $where = "WHERE
        CBLocal.asset_tag LIKE '%$safe_search%' OR
        CBLocal.serial_number LIKE '%$safe_search%' OR
        CBLocal.model LIKE '%$safe_search%' OR
        CBLocal.student_id LIKE '%$safe_search%' OR /* This searches by student ID */
        CBLocal.location LIKE '%$safe_search%'";
}

// --- Pagination ---
$per_page = 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Count total for pagination
$count_q = "SELECT COUNT(*) FROM CBLocal $where";
$total_results = mysqli_fetch_row(mysqli_query($dbc, $count_q))[0];
$total_pages = max(1, ceil($total_results / $per_page));

// Get current page results (join CBCart for cart_name)
// Also join CATstudent to get student names for display and search (if student name search is implemented)
$devices_q = "SELECT CBLocal.*, CBCart.cart_name, CATstudent.firstName, CATstudent.lastName 
              FROM CBLocal 
              LEFT JOIN CBCart ON CBLocal.checked_out_cart = CBCart.cart_id
              LEFT JOIN CATstudent ON CBLocal.student_id = CATstudent.studentID
              $where 
              ORDER BY CBLocal.asset_tag ASC LIMIT $per_page OFFSET $offset";

// If searching by student name, the $where clause needs to be more complex, potentially:
if ($search) {
    $safe_search = mysqli_real_escape_string($dbc, $search);
    $where_clauses = [
        "CBLocal.asset_tag LIKE '%$safe_search%'",
        "CBLocal.serial_number LIKE '%$safe_search%'",
        "CBLocal.model LIKE '%$safe_search%'",
        "CBLocal.student_id LIKE '%$safe_search%'", // Keep search by student ID
        "CBLocal.location LIKE '%$safe_search%'",
        "CATstudent.firstName LIKE '%$safe_search%'", // Search by student first name
        "CATstudent.lastName LIKE '%$safe_search%'",  // Search by student last name
        "CONCAT(CATstudent.firstName, ' ', CATstudent.lastName) LIKE '%$safe_search%'" // Search by full name
    ];
    $where = "WHERE (" . implode(' OR ', $where_clauses) . ")";

    // Recount total for pagination with the new WHERE clause including student names
    $count_q = "SELECT COUNT(DISTINCT CBLocal.asset_tag) FROM CBLocal 
                LEFT JOIN CATstudent ON CBLocal.student_id = CATstudent.studentID 
                $where";
    $total_results = mysqli_fetch_row(mysqli_query($dbc, $count_q))[0];
    $total_pages = max(1, ceil($total_results / $per_page));
    
    // Re-run the main query with the refined WHERE for student name search
    $devices_q = "SELECT CBLocal.*, CBCart.cart_name, CATstudent.firstName, CATstudent.lastName 
                  FROM CBLocal 
                  LEFT JOIN CBCart ON CBLocal.checked_out_cart = CBCart.cart_id
                  LEFT JOIN CATstudent ON CBLocal.student_id = CATstudent.studentID
                  $where 
                  ORDER BY CBLocal.asset_tag ASC LIMIT $per_page OFFSET $offset";
}
$devices = mysqli_query($dbc, $devices_q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Devices | Chromebook System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background: #181A20; color: #EDEDED; }
        .card {
            background: #23272F;
            border: none;
            box-shadow: 0 4px 18px #10121a22;
        }
        .card-title, h3, h4, label { color: #ffcb4b !important; }
        .form-control {
            background: #21232b;
            color: #fff;
            border: 1px solid #555;
            font-size: 1.13em;
        }
        .form-control:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.08rem #ffcb4b66; }
        .form-control::placeholder { color: #EDEDED !important; opacity: 1; }
        .device-link { color: #3a80d9; text-decoration: underline; font-weight: 600;}
        .device-link:hover { color: #ffcb4b; text-decoration: underline; }
        .card { max-width: 1100px; margin: 0 auto; width: 100%; }
        .table thead th {
            background: #23272F;
            color: #ffcb4b !important;
            font-weight: 700;
            font-size: 1.13em;
            border-bottom: 2px solid #555;
        }
        .table tbody tr { color: #fff; font-size: 1.13em; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #252834 !important; }
        .btn, .form-control { font-size: 1.11rem; }
        .navbar-toggler { border: none; }
        .navbar-toggler:focus { outline: none; box-shadow: none; }
        .sidebar-offcanvas { min-width:220px; background:#23272F; color:#fff; height:100vh; }
        .sidebar h3, .sidebar-heading { color: #ffcb4b; }
        /* Responsive form on mobile */
        @media (max-width: 767px) {
            .search-form .col-md-6,
            .search-form .col-md-2,
            .search-form .col-auto {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .search-form > .col-auto,
            .search-form > .col-md-2,
            .search-form > .col-md-6 {
                margin-bottom: 0.5rem;
            }
        }
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        /* Pagination */
        .pagination .page-link { background: #23272F; color: #fff; border: 1px solid #444; }
        .pagination .page-item.active .page-link { background: #ffcb4b; color: #181A20; border: 1px solid #ffcb4b; }
        .pagination .page-item.disabled .page-link { background-color: #2a2d35; border-color: #444; color: #6c757d;} /* Style for disabled links */

        /* SIDEBAR FIX */
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
        .modal-content { background: #23272F; color: #fff; }
        .btn-success { background: #27ae60; color: #fff; border: none; }
        .btn-success:hover { background: #219150; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark" style="background:#23272F;">
    <div class="container-fluid">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand ms-2" style="color:#ffcb4b; font-weight: bold;">CATS - Chromebook Asset Tracking System</span>
    </div>
</nav>
<div class="d-flex">
    <div class="sidebar-offcanvas d-none d-lg-flex flex-column p-3">
        <?php include('sidebar.php'); ?>
    </div>
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel" style="background:#23272F; color:#fff;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasSidebarLabel" style="color:#ffcb4b;">CATS Menu</h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-3">
            <?php include('sidebar.php'); ?>
        </div>
    </div>
    <div class="flex-grow-1 p-2 p-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3 fs-3">Device Inventory</h4>
                <form class="mb-3 row g-2 align-items-center search-form" method="get" action="devices.php">
                    <div class="col-12 col-md-6 mb-2 mb-md-0">
                        <input type="text" id="search_input" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-lg" placeholder="Search by asset, serial, model, location, or student...">
                    </div>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <button class="btn btn-warning w-100 btn-lg" type="submit"><i class="fa fa-search"></i> Search</button>
                    </div>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <button type="button" class="btn btn-success w-100 btn-lg" onclick="showBarcodeScanner()">
                            <i class="fa fa-barcode"></i> Scan Barcode
                        </button>
                    </div>
                    <?php if($search): ?>
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <a href="devices.php" class="btn btn-secondary w-100 btn-lg"><i class="fa fa-times"></i> Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
                <div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="barcodeModalLabel">Scan Barcode</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                                onclick="closeBarcodeScanner()"></button>
                      </div>
                      <div class="modal-body text-center">
                        <div id="qr-reader" style="width: 100%;"></div>
                        <div id="qr-reader-results" style="margin-top: 10px;"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Serial Number</th>
                                <th>Model</th>
                                <th>Cart</th>
                                <th>Status</th>
                                <th>Checked Out</th>
                                <th>Loaner</th>
                                <th>Student Name</th>
                                <th>Notes</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($devices && mysqli_num_rows($devices) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($devices)) {
                                    $student_name = '';
                                    if ($row['is_checked_out'] && !empty($row['student_id'])) {
                                        // Student name is now fetched directly from the JOIN in the main query
                                        if (!empty($row['firstName']) || !empty($row['lastName'])) {
                                            $student_name = trim(htmlspecialchars($row['firstName'] . ' ' . $row['lastName']));
                                        } else {
                                            // Fallback if name is not found but ID exists (should be rare with LEFT JOIN)
                                            $student_name = 'ID: ' . htmlspecialchars($row['student_id']);
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <a href="device_details.php?asset_tag=<?= urlencode($row['asset_tag']) ?>" class="device-link" title="View device details">
                                                <?= htmlspecialchars($row['asset_tag']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($row['serial_number']) ?></td>
                                        <td><?= htmlspecialchars($row['model']) ?></td>
                                        <td><?= htmlspecialchars($row['cart_name']) ?></td>
                                        <td><?= htmlspecialchars($row['status']) ?></td>
                                        <td><?= $row['is_checked_out'] ? "Yes" : "No" ?></td>
                                        <td><?= $row['is_loaner'] ? "Yes" : "No" ?></td>
                                        <td><?= htmlspecialchars($student_name) ?></td>
                                        <td><?= htmlspecialchars($row['notes']) ?></td>
                                        <td>
                                            <a href="edit_device.php?asset_tag=<?= urlencode($row['asset_tag']) ?>" class="btn btn-sm btn-secondary"><i class="fa fa-edit"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center">No devices found<?php if ($search) echo " matching your search"; ?>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mt-3 justify-content-center flex-wrap">

                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ($page - 1)])) ?>">Previous</a>
                            </li>

                            <?php
                            $window = 2; // Number of pages to show before and after the current page

                            // First page link and leading ellipsis
                            if (max(1, $page - $window) > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                if (max(1, $page - $window) > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            // Page numbers
                            for ($p = max(1, $page - $window); $p <= min($total_pages, $page + $window); $p++) {
                                ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                                <?php
                            }

                            // Trailing ellipsis and last page link
                            if (min($total_pages, $page + $window) < $total_pages) {
                                if (min($total_pages, $page + $window) < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ($page + 1)])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showBarcodeScanner() {
    let modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    modal.show();

    // Init scanner only once per open
    setTimeout(function() {
        // Check if the scanner element is already initialized by looking for a canvas or video
        const readerDiv = document.getElementById('qr-reader');
        if (readerDiv && !readerDiv.dataset.qrScannerInitialized) {
            readerDiv.dataset.qrScannerInitialized = 'true'; // Mark as initializing
            
            let lastResult = '';
            const html5QrCode = new Html5Qrcode("qr-reader");
            window.html5QrCodeInstance = html5QrCode; // Store instance globally to manage it

            html5QrCode.start(
                { facingMode: "environment" }, // Prefer rear camera
                { fps: 10, qrbox: {width: 250, height: 250} }, // Adjusted qrbox to be an object
                (decodedText, decodedResult) => {
                    if (decodedText !== lastResult) {
                        lastResult = decodedText;
                        document.getElementById('search_input').value = decodedText;
                        
                        if (window.html5QrCodeInstance) {
                            window.html5QrCodeInstance.stop().then(() => {
                                delete readerDiv.dataset.qrScannerInitialized; // Clear init flag
                                // Auto-submit the form
                                document.querySelector('form.search-form').submit();
                                modal.hide(); // Hide modal after successful scan and submission
                            }).catch(err => {
                                console.error("Error stopping the scanner:", err);
                                delete readerDiv.dataset.qrScannerInitialized;
                            });
                        }
                    }
                },
                (errorMessage) => {
                    // console.log("QR Scanner Error:", errorMessage);
                }
            ).catch(err => {
                console.error("Unable to start QR scanner:", err);
                delete readerDiv.dataset.qrScannerInitialized; // Clear init flag on error
            });
        }
    }, 300); // Delay to ensure modal is visible
}

function closeBarcodeScanner() {
    const readerDiv = document.getElementById('qr-reader');
    if (window.html5QrCodeInstance) {
        window.html5QrCodeInstance.stop().then(() => {
            // console.log("QR Scanner stopped successfully.");
            if (readerDiv) delete readerDiv.dataset.qrScannerInitialized;
        }).catch(err => {
            console.error("Error stopping the QR scanner on close:", err);
            // Even if stop fails, mark as not initialized
            if (readerDiv) delete readerDiv.dataset.qrScannerInitialized;
        });
    } else {
         if (readerDiv) delete readerDiv.dataset.qrScannerInitialized; // Ensure flag is cleared if instance was not set
    }
}

// Ensure scanner stops when modal is closed (by clicking X, ESC, or outside)
document.getElementById('barcodeModal').addEventListener('hidden.bs.modal', function () {
    closeBarcodeScanner();
});

// Optional: auto focus on search input if not on a touch device (enhancement)
// const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
// if (!isTouchDevice) {
//    const searchInput = document.getElementById('search_input');
//    if (searchInput) searchInput.focus();
// }
</script>
</body>
</html>
