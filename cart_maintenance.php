<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}
include('/var/cbinfo_connect.php');

$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : null;
$msg = '';
$move_mode = isset($_POST['find_device']) || isset($_POST['move_device_submit']);
$move_result = '';
$found_device = null;
$found_cart = null;

// Step 1: Select cart
$cart = null;
if ($cart_id && !$move_mode) {
    $cart_res = mysqli_query($dbc, "SELECT * FROM CBCart WHERE cart_id=$cart_id");
    $cart = mysqli_fetch_assoc($cart_res);
}

// Step 2: Handle Add Device
if ($cart && isset($_POST['add_device'])) {
    $asset_tag = strtoupper(trim($_POST['asset_tag']));
    $res = mysqli_query($dbc, "SELECT checked_out_cart, student_id FROM CBLocal WHERE asset_tag='$asset_tag' LIMIT 1");
    $row = mysqli_fetch_assoc($res);

    if ($row) {
        if ($row['student_id']) {
            $msg = "Device $asset_tag is currently assigned to a student and cannot be moved to a cart!";
        } elseif ($row['checked_out_cart'] && $row['checked_out_cart'] != $cart_id) {
            mysqli_query($dbc, "UPDATE CBLocal SET checked_out_cart=$cart_id WHERE asset_tag='$asset_tag'");
            mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, cart_id, notes, time) VALUES ('Moved to Cart', '$asset_tag', $cart_id, 'Moved from cart {$row['checked_out_cart']} to cart $cart_id.', NOW())");
            $msg = "Device $asset_tag moved from another cart to this cart.";
        } elseif ($row['checked_out_cart'] == $cart_id) {
            $msg = "Device $asset_tag is already in this cart.";
        } else {
            mysqli_query($dbc, "UPDATE CBLocal SET checked_out_cart=$cart_id WHERE asset_tag='$asset_tag'");
            mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, cart_id, notes, time) VALUES ('Assigned to Cart', '$asset_tag', $cart_id, 'Assigned to cart $cart_id.', NOW())");
            $msg = "Device $asset_tag assigned to cart.";
        }
    } else {
        $msg = "Device $asset_tag not found in inventory!";
    }
}

// Step 3: Remove Device from Cart
if ($cart && isset($_POST['remove_device'])) {
    $asset_tag = $_POST['remove_device'];
    mysqli_query($dbc, "UPDATE CBLocal SET checked_out_cart=NULL WHERE asset_tag='$asset_tag'");
    mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, cart_id, notes, time) VALUES ('Removed from Cart', '$asset_tag', $cart_id, 'Removed from cart $cart_id.', NOW())");
    $msg = "Device $asset_tag removed from this cart.";
}

// Step 4: Find/Move Device by Asset Tag
if ($move_mode) {
    $move_asset_tag = strtoupper(trim($_POST['move_asset_tag'] ?? ''));
    if ($move_asset_tag) {
        $found_res = mysqli_query($dbc, "SELECT * FROM CBLocal WHERE asset_tag='$move_asset_tag' LIMIT 1");
        $found_device = mysqli_fetch_assoc($found_res);
        if ($found_device && $found_device['checked_out_cart']) {
            $cart_res = mysqli_query($dbc, "SELECT * FROM CBCart WHERE cart_id=" . intval($found_device['checked_out_cart']));
            $found_cart = mysqli_fetch_assoc($cart_res);
        }
    }
    // Move device to new cart if submitted
    if (isset($_POST['move_device_submit']) && $found_device) {
        $new_cart_id = intval($_POST['new_cart_id']);
        $old_cart_id = $found_device['checked_out_cart'];
        mysqli_query($dbc, "UPDATE CBLocal SET checked_out_cart=$new_cart_id WHERE asset_tag='$move_asset_tag'");
        mysqli_query($dbc, "INSERT INTO CBEvents (Event, assignedID, cart_id, notes, time) VALUES ('Moved to Cart', '$move_asset_tag', $new_cart_id, 'Moved from cart $old_cart_id to cart $new_cart_id.', NOW())");
        $move_result = "Device $move_asset_tag moved from cart $old_cart_id to cart $new_cart_id.";
        // Reload device info after move
        $found_res = mysqli_query($dbc, "SELECT * FROM CBLocal WHERE asset_tag='$move_asset_tag' LIMIT 1");
        $found_device = mysqli_fetch_assoc($found_res);
        if ($found_device && $found_device['checked_out_cart']) {
            $cart_res = mysqli_query($dbc, "SELECT * FROM CBCart WHERE cart_id=" . intval($found_device['checked_out_cart']));
            $found_cart = mysqli_fetch_assoc($cart_res);
        } else {
            $found_cart = null;
        }
    }
}

// Fetch devices in this cart
$devices = [];
if ($cart_id && !$move_mode) {
    $devices_res = mysqli_query($dbc, "SELECT * FROM CBLocal WHERE checked_out_cart=$cart_id ORDER BY asset_tag ASC");
    while ($row = mysqli_fetch_assoc($devices_res)) {
        $devices[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart Maintenance | Chromebook System</title>
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
        .alert-info { background: #323741; color: #ffcb4b; border: none;}
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
        <span class="navbar-brand ms-2" style="color:#ffcb4b; font-weight: bold;">CATS</span>
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
        <div class="card p-4 mx-auto" style="max-width: 750px;">
            <h2>Cart Maintenance</h2>
            <?php if($msg): ?>
                <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if($move_result): ?>
                <div class="alert alert-info"><?= htmlspecialchars($move_result) ?></div>
            <?php endif; ?>

            <!-- Main options -->
            <?php if (!$cart && !$move_mode): ?>
                <div class="mb-4 d-flex flex-column flex-md-row gap-4">
                    <!-- Existing Cart Option -->
                    <div style="flex:1;min-width:220px;">
                        <form method="POST">
                            <h4>View/Edit Cart</h4>
                            <select name="cart_id" class="form-control mb-2" required>
                                <option value="">-- Choose Cart --</option>
                                <?php
                                $carts = mysqli_query($dbc, "SELECT * FROM CBCart ORDER BY cart_name ASC");
                                while ($c = mysqli_fetch_assoc($carts)):
                                ?>
                                <option value="<?= $c['cart_id'] ?>">
                                    <?= htmlspecialchars($c['cart_name']) ?><?= $c['location'] ? ' ('.$c['location'].')' : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-warning">Load Cart</button>
                        </form>
                    </div>
                    <!-- Find/Move Device Option -->
                    <div style="flex:1;min-width:220px;">
                        <form method="POST">
                            <h4>Find/Move Device</h4>
                            <input type="text" name="move_asset_tag" id="move_asset_tag" class="form-control mb-2" placeholder="Scan or enter asset tag..." required>
                            <button type="submit" name="find_device" class="btn btn-success">Find Device</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Find/Move Device Screen -->
            <?php if($move_mode): ?>
                <form method="POST" class="mb-4">
                    <h4>Find/Move Device</h4>
                    <input type="text" name="move_asset_tag" id="move_asset_tag" class="form-control mb-2" value="<?= htmlspecialchars($_POST['move_asset_tag'] ?? '') ?>" placeholder="Scan or enter asset tag..." required>
                    <button type="submit" name="find_device" class="btn btn-success mb-3">Find Device</button>
                    <?php if($found_device): ?>
                        <div class="mb-2">
                            <strong>Device:</strong> <?= htmlspecialchars($found_device['asset_tag']) ?>
                        </div>
                        <div class="alert alert-info py-2 mb-3" style="font-size:1.1em;">
                            <strong>Current Cart:</strong>
                            <?php if($found_cart): ?>
                                <?= htmlspecialchars($found_cart['cart_name']) ?>
                                <?= $found_cart['location'] ? ' ('.htmlspecialchars($found_cart['location']).')' : '' ?>
                                [ID: <?= $found_cart['cart_id'] ?>]
                            <?php else: ?>
                                <span style="color:#ffcb4b;">Not in any cart</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <label for="new_cart_id">Move to Cart:</label>
                            <select name="new_cart_id" id="new_cart_id" class="form-control mb-2" required>
                                <option value="">-- Select Cart --</option>
                                <?php
                                $carts = mysqli_query($dbc, "SELECT * FROM CBCart ORDER BY cart_name ASC");
                                while ($c = mysqli_fetch_assoc($carts)):
                                ?>
                                <option value="<?= $c['cart_id'] ?>" <?= ($found_cart && $c['cart_id']==$found_cart['cart_id']) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($c['cart_name']) ?><?= $c['location'] ? ' ('.$c['location'].')' : '' ?>
                                    <?= ($found_cart && $c['cart_id']==$found_cart['cart_id']) ? ' (current)' : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="move_device_submit" class="btn btn-warning">Move Device</button>
                        </div>
                    <?php elseif(isset($_POST['find_device'])): ?>
                        <div class="alert alert-danger">Device not found.</div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="cart_maintenance.php" class="btn btn-secondary">Back to Cart Maintenance</a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Show cart devices (if loaded) -->
            <?php if ($cart): ?>
                <h4><?= htmlspecialchars($cart['cart_name']) ?><?= $cart['location'] ? ' ('.$cart['location'].')' : '' ?></h4>
                <form method="POST" class="mb-3 d-flex flex-row flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                    <input type="text" name="asset_tag" id="asset_tag" class="form-control" placeholder="Scan or enter asset tag..." inputmode="text" pattern="[A-Za-z0-9\-]*" style="max-width: 220px;">
                    <button type="submit" name="add_device" class="btn btn-success">Add Device</button>
                    <button type="button" class="btn btn-warning" onclick="showBarcodeScanner()"><i class="fa fa-barcode"></i> Scan Barcode</button>
                </form>
                <div class="device-list">
                    <strong>Devices in Cart:</strong>
                    <ul class="list-unstyled">
                        <?php foreach ($devices as $dev): ?>
                            <li>
                                <?= htmlspecialchars($dev['asset_tag']) ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                                    <button type="submit" name="remove_device" value="<?= htmlspecialchars($dev['asset_tag']) ?>" class="btn-remove" title="Remove">&times;</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$devices): ?>
                            <li style="color:#ffcb4b;">No devices assigned to this cart.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="mt-3">
                    <a href="cart_maintenance.php" class="btn btn-secondary">Back to Main</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Barcode Scanner Modal (re-using html5-qrcode) -->
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
document.getElementById('barcodeModal').addEventListener('hidden.bs.modal', closeBarcodeScanner);

// ---- Autofocus enhancements ----
document.addEventListener("DOMContentLoaded", function() {
    var assetInput = document.getElementById('asset_tag');
    var moveInput = document.getElementById('move_asset_tag');
    if (assetInput) {
        assetInput.focus();
    } else if (moveInput) {
        moveInput.focus();
    }
});
</script>
</body>
</html>
