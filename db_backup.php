<?php
include('session_check.php');
if (!isset($_SESSION['email'])) {
    die('Unauthorized');
}

// --- CONFIGURATION --- //
$db_host = 'localhost';
$db_user = 'YOUR_DB_USER';
$db_pass = 'YOUR_DB_PASSWORD';
$db_name = 'YOUR_DB_NAME';

// Store backups in ./backups (protected or outside web root in production!)
$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0700, true);

// --- Backup creation --- //
$msg = '';
if (isset($_POST['do_backup'])) {
    $date = date('Ymd_His');
    $filename = "CATS_backup_{$date}.sql";
    $filepath = "$backup_dir/$filename";
    $cmd = sprintf(
        'mysqldump --user=%s --password=%s --host=%s --single-transaction --quick --lock-tables=false %s > %s 2>&1',
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_host),
        escapeshellarg($db_name),
        escapeshellarg($filepath)
    );
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    if ($return_var === 0 && file_exists($filepath)) {
        $msg = "Backup created: <strong>$filename</strong>";
    } else {
        $msg = "<span style='color:#e6657a;'>Backup failed.</span><br><pre>".htmlspecialchars(implode("\n", $output))."</pre>";
    }
}

// --- Download handler --- //
if (isset($_GET['download'])) {
    $f = basename($_GET['download']);
    $filepath = "$backup_dir/$f";
    if (preg_match('/\.sql$/', $f) && file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        die("File not found.");
    }
}

// --- List available backups --- //
$backups = [];
foreach (glob("$backup_dir/*.sql") as $file) {
    $backups[] = basename($file);
}
usort($backups, function($a, $b) {
    return strcmp($b, $a); // Newest first
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Backup | Chromebook System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .container { max-width: 600px; margin-top: 3em; }
        .card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; border-radius: 1.1em; }
        h2 { color: #ffcb4b; }
        .btn-warning, .btn-warning:visited { background: #ffcb4b; color: #181A20; border: none; font-weight: bold;}
        .btn-warning:hover { background: #ffd900; }
        .btn-secondary { color: #fff; }
        .msg { font-size:1.09em; margin-bottom:1.2em; color:#36d99b; }
        .table thead th { color: #ffcb4b; background: #23272F; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #252834 !important; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4 mt-4">
        <h2>Database Backup</h2>
        <p>Click below to create a new backup of the CATS database. Recent backups are listed below for download.</p>
        <?php if($msg): ?>
            <div class="msg"><?= $msg ?></div>
        <?php endif; ?>
        <form method="post">
            <button class="btn btn-warning mb-3" type="submit" name="do_backup" value="1">
                <i class="fa fa-database"></i> Create New Backup
            </button>
            <a href="settings.php" class="btn btn-secondary ms-2 mb-3"><i class="fa fa-arrow-left"></i> Back to Settings</a>
        </form>
        <h5 class="mt-4 mb-3">Recent Backups</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Date</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($backups)): ?>
                        <?php foreach($backups as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b) ?></td>
                                <td><?= date('Y-m-d H:i:s', filemtime("$backup_dir/$b")) ?></td>
                                <td>
                                    <a href="db_backup.php?download=<?= urlencode($b) ?>" class="btn btn-sm btn-warning">
                                        <i class="fa fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center">No backups found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted mt-3" style="font-size:0.96em;">
            For your protection, <b>store backups off-server</b> regularly.
        </div>
    </div>
</div>
</body>
</html>
