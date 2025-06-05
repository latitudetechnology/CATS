<div class="sidebar d-flex flex-column" style="height: 100%; background: #23272F;">
    <ul class="nav flex-column mb-auto">
        <li>
            <a href="index.php" class="nav-link<?= (in_array(basename($_SERVER['PHP_SELF']), ['', 'index.php'])) ? ' active' : '' ?>">
                <i class="fa fa-chart-bar"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="devices.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'devices.php') ? ' active' : '' ?>">
                <i class="fa fa-laptop"></i> Devices
            </a>
        </li>
        <li>
            <a href="workorders.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'workorders.php') ? ' active' : '' ?>">
                <i class="fa fa-tools"></i> Work Orders
            </a>
        </li>
        <li>
            <a href="checkinout.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'checkinout.php') ? ' active' : '' ?>">
                <i class="fa fa-exchange-alt"></i> Check In/Out
            </a>
        </li>
        <li>
            <a href="students.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'students.php') ? ' active' : '' ?>">
                <i class="fa fa-user-graduate"></i> Students
            </a>
        </li>
        <li>
            <a href="sync_devices.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'sync_devices.php') ? ' active' : '' ?>">
                <i class="fa fa-download"></i> Import Devices
            </a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li>
            <a href="reports.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'reports.php') ? ' active' : '' ?>">
                <i class="fa fa-file-alt"></i> Reports
            </a>
        </li>
        <li>
            <a href="settings.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'settings.php') ? ' active' : '' ?>">
                <i class="fa fa-cog"></i> Settings
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <hr>
    <div class="sidebar-heading" style="font-size: 1em; color: #ffcb4b;">Cart Management</div>
    <ul class="nav flex-column mb-auto">
        <li>
            <a href="cart_loader.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'cart_loader.php') ? ' active' : '' ?>">
                <i class="fa fa-dolly"></i> Cart Loader
            </a>
        </li>
        <li>
            <a href="cart_maintenance.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'cart_maintenance.php') ? ' active' : '' ?>">
                <i class="fa fa-wrench"></i> Cart Maintenance
            </a>
        </li>
    </ul>

    <hr>
    <div class="sidebar-heading" style="font-size: 1em; color: #ffcb4b;">Student Kiosk</div>
    <ul class="nav flex-column mb-auto">
        <li>
            <a href="student_kiosk.php" class="nav-link<?= (basename($_SERVER['PHP_SELF']) == 'student_kiosk.php') ? ' active' : '' ?>">
                <i class="fa fa-desktop"></i> Student Kiosk
            </a>
        </li>
    </ul>

    <!-- User Info Section -->
    <div class="mt-auto pt-3 border-top" style="font-size: 0.95em;">
        <?php if (isset($_SESSION['name']) && isset($_SESSION['email'])): ?>
            <div>
                <i class="fa fa-user-circle"></i>
                <strong><?= htmlspecialchars($_SESSION['name']); ?></strong>
                <br>
                <span style="font-size:0.95em; color:#ffcb4b;"><?= htmlspecialchars($_SESSION['email']); ?></span>
                <?php if (isset($_SESSION['role'])): ?>
                    <br>
                    <span class="badge <?= ($_SESSION['role']=='admin') ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                        <?= ucfirst($_SESSION['role']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="mt-2">
                <a href="logout.php" class="btn btn-sm btn-outline-light"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
        <?php else: ?>
            <span class="text-danger">Not signed in</span>
        <?php endif; ?>
    </div>
</div>
