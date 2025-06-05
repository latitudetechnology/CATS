<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

// 1. Init session device list
if (!isset($_SESSION['cart_loader'])) $_SESSION['cart_loader'] = [];
$scanned_devices = &$_SESSION['cart_loader'];
$msg = '';
$cart_exists = false;
$cart_id = null;
$cart_name = '';
$location = '';
$barcode = '';
$just_finished = false;

// Always try to get cart info if cart_id set (any POST after choosing cart will have cart_id)
if (isset($_POST['cart_id'])) {
    $cart_id = intval($_POST['cart_id']);
    $cart_res = mysqli_query($dbc, "SELECT * FROM CBCart WHERE cart_id=$cart_id");
    if ($cart = mysqli_fetch_assoc($cart_res)) {
        $cart_exists = true;
        $cart_name = $cart['cart_name'];
        $location = $cart['location'];
        $barcode = $cart['barcode'];
    }
}

// Start new cart (resets device list)
if (isset($_POST['start_cart'])) {
    $cart_id = intval($_POST['cart_id']);
    $cart_res = mysqli_query($dbc, "SELECT * FROM CBCart WHERE cart_id=$cart_id");
    if ($cart = mysqli_fetch_assoc($cart_res)) {
        $cart_exists = true;
        $cart_name = $cart['cart_name'];
        $location = $cart['location'];
        $barcode = $cart['barcode'];
        $scanned_devices = []; // Reset session list
    }
}

// Create new cart (resets device list)
if (isset($_POST['create_cart'])) {
    $cart_name = trim($_POST['cart_name']);
    $location = trim($_POST['location']);
    $barcode = trim($_POST['barcode']);
    if ($cart_name) {
        mysqli_query($dbc, "INSERT INTO CBCart (cart_name, barcode, location) VALUES ('$cart_name', '$barcode', '$location')");
        $cart_id = mysqli_insert_id($dbc);
        $cart_exists = true;
        $scanned_devices = [];
    }
}

// Add scanned device to session list (do NOT clear device list here)
if ($cart_exists && isset($_POST['device_scan'])) {
    $asset_tag = strtoupper(trim($_POST['asset_tag']));
    if ($asset_tag && !in_array($asset_tag, $scanned_devices)) {
        $res = mysqli_query($dbc, "SELECT checked_out_cart FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1");
        $existing = mysqli_fetch_assoc($res);
        if ($existing && $existing['checked_out_cart'] && $existing['checked_out_cart'] != $cart_id) {
            $msg = "Warning: $asset_tag is already assigned to another cart. Will move if you continue.";
        }
        $scanned_devices[] = $asset_tag;
    }
}

// Remove device from list
if ($cart_exists && isset($_POST['remove_device'])) {
    $asset_tag = $_POST['remove_device'];
    $scanned_devices = array_diff($scanned_devices, [$asset_tag]);
    $scanned_devices = array_values($scanned_devices);
}

// Save and assign devices to cart
if ($cart_exists && isset($_POST['finish'])) {
    foreach ($scanned_devices as $asset_tag) {
        $res = mysqli_query($dbc, "SELECT checked_out_cart, student_id FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        $prev_cart = $row['checked_out_cart'];
        $prev_student = $row['student_id'];

        mysqli_query($dbc, "UPDATE CBLocal SET checked_out_cart=$cart_id, student_id=NULL WHERE asset_tag='$asset_tag'");

        $note = "";
        if ($prev_cart && $prev_cart != $cart_id) $note .= "Moved from cart $prev_cart. ";
        if ($prev_student) $note .= "Removed from student $prev_student. ";
        $note .= "Assigned to cart $cart_id.";
        mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, cart_id, notes, time) VALUES ('Moved to Cart', '$asset_tag', $cart_id, '$note', NOW())");
    }
    $msg = "All devices assigned to cart!";
    $scanned_devices = [];
    $cart_exists = false; // Reset to cart select screen
    $just_finished = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart Loader | Chromebook System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background: #181A20; color: #EDEDED; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; }
        h2, h4, label { color: #ffcb4b; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .btn-success { color: #fff; background: #27ae60; border: none; }
        .btn-success:hover { background: #219150; }
        .device-list { background: #212529; border-radius: 8px; padding: 1em; margin-top: 1em;}
        .device-list li { color: #fff; margin-bottom: 0.5em; font-size: 1.1em;}
        .btn-remove { color: #fff; background: #c0392b; border: none; padding: 0.2em 0.5em; margin-left: 1em; border-radius: 4px;}
        .btn-remove:hover { background: #a93226; }
        .form-control { background: #23272F; color: #fff; border: 1px solid #444; }
        .form-control:focus { border-color: #ffcb4b; box-shadow: 0 0 0 0.1rem #ffcb4b44; }
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
        .alert-info { background: #323741; color: #ffcb4b; border: none;}
        @media (max-width: 991.98px) {
            .sidebar-offcanvas { display: none; }
        }
        @media (min-width: 992px) {
            #offcanvasSidebar { display: none !important; }
        }
        @media (max-width: 767px) {
            .card { padding: 0.7em 0.4em; }
            h2 { font-size: 1.1em; }
            .device-list li { font-size: 1em; }
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
        <div class="card p-4 mx-auto" style="max-width: 650px;">
            <h2>Cart Loader</h2>
            <?php if($msg): ?>
                <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
                <?php if($just_finished): ?>
                <script>
                    setTimeout(function() { window.location = window.location.pathname; }, 1600);
                </script>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Step 1: Select or Create Cart -->
            <?php if (!$cart_exists): ?>
                <form method="POST" class="mb-4">
                    <h4>Select Existing Cart</h4>
                    <select name="cart_id" class="form-control mb-3" required>
                        <option value="">-- Choose Cart --</option>
                        <?php
                        $carts = mysqli_query($dbc, "SELECT * FROM CBCart ORDER BY cart_name ASC");
                        while ($cart = mysqli_fetch_assoc($carts)):
                        ?>
                        <option value="<?= $cart['cart_id'] ?>"><?= htmlspecialchars($cart['cart_name']) ?><?= $cart['location'] ? ' ('.$cart['location'].')' : '' ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" name="start_cart" class="btn btn-warning">Load Cart</button>
                </form>
                <form method="POST" class="mb-4">
                    <h4>Or Create New Cart</h4>
                    <div class="mb-2">
                        <label>Cart Name</label>
                        <input type="text" name="cart_name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Barcode (optional)</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Location (optional)</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <button type="submit" name="create_cart" class="btn btn-success">Create Cart</button>
                </form>
            <?php else: ?>
                <!-- Step 2: Scan/Add Devices to Cart -->
                <h4>Cart: <?= htmlspecialchars($cart_name) ?><?= $location ? ' ('.$location.')' : '' ?></h4>
                <form method="POST" class="mb-3 d-flex flex-row flex-wrap gap-2" style="align-items:center;">
                    <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                    <input type="text" name="asset_tag" id="asset_tag" class="form-control" placeholder="Scan or enter asset tag..." autofocus inputmode="text" pattern="[A-Za-z0-9\-]*" style="max-width: 220px;">
                    <button type="submit" name="device_scan" class="btn btn-warning">Add Device</button>
                    <button type="button" class="btn btn-success" onclick="showBarcodeScanner()"><i class="fa fa-barcode"></i> Scan Barcode</button>
                </form>
                <!-- Device list -->
                <div class="device-list">
                    <strong>Scanned Devices:</strong>
                    <ul class="list-unstyled">
                        <?php foreach ($scanned_devices as $tag): ?>
                            <li>
                                <?= htmlspecialchars($tag) ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                                    <button type="submit" name="remove_device" value="<?= htmlspecialchars($tag) ?>" class="btn-remove" title="Remove">&times;</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$scanned_devices): ?>
                            <li style="color:#ffcb4b;">No devices added yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                    <button type="submit" name="finish" class="btn btn-primary">Finish and Assign Devices to Cart</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showBarcodeScanner() {
    let modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    modal.show();

    setTimeout(function() {
        if (!window.qrScannerInitialized) {
            window.qrScannerInitialized = true;
            let lastResult = '';
            const html5QrCode = new Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                (decodedText, decodedResult) => {
                    if (decodedText !== lastResult) {
                        lastResult = decodedText;
                        modal.hide();
                        html5QrCode.stop().then(() => {
                            document.getElementById('qr-reader').innerHTML = "";
                            window.qrScannerInitialized = false;
                            document.getElementById('asset_tag').value = decodedText;
                            setTimeout(function(){
                                document.getElementById('asset_tag').form.submit();
                            }, 100);
                        });
                    }
                },
                (errorMessage) => {}
            );
            window.html5QrCode = html5QrCode;
        }
    }, 300);
}

function closeBarcodeScanner() {
    if (window.html5QrCode) {
        window.html5QrCode.stop().then(() => {
            document.getElementById('qr-reader').innerHTML = "";
            window.qrScannerInitialized = false;
        });
    }
}
document.getElementById('barcodeModal')?.addEventListener('hidden.bs.modal', closeBarcodeScanner);
</script>
<!-- Barcode Scanner Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#23272F; color:#fff;">
      <div class="modal-header">
        <h5 class="modal-title" id="barcodeModalLabel">Scan Device Barcode</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                onclick="closeBarcodeScanner()"></button>
      </div>
      <div class="modal-body text-center">
        <div id="qr-reader" style="width: 100%;"></div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
