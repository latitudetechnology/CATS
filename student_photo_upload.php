<?php
include('session_check.php');
if (!isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div style='color:orange'>Admin access required.</div>";
    exit;
}
$msg = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    if (!preg_match('/^\d+$/', $student_id)) {
        $msg = "<div class='alert alert-danger'>Invalid Student ID. Must be all numbers.</div>";
    } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $msg = "<div class='alert alert-danger'>No photo selected or upload error.</div>";
    } else {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/pjpeg'];
        if (!in_array($file['type'], $allowed_types)) {
            $msg = "<div class='alert alert-danger'>Only JPG/JPEG files are allowed.</div>";
        } elseif ($file['size'] > 1024 * 1024) { // 1 MB limit
            $msg = "<div class='alert alert-danger'>Image too large. Max allowed: 1MB.</div>";
        } else {
            $target_dir = __DIR__ . '/student_photos/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $target_file = $target_dir . $student_id . '.jpg';
            // Optionally, resize if needed (for now, just move the file)
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $msg = "<div class='alert alert-success'>Photo uploaded for Student ID <b>$student_id</b>.</div>";
                $success = true;
            } else {
                $msg = "<div class='alert alert-danger'>Upload failed (permissions issue?).</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Single Student Photo Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        .container { max-width: 500px; margin-top: 3em; }
        .card { background: #23272F; border: none; color: #fff; }
        h3 { color: #ffcb4b; }
        label { color: #ffcb4b; }
        .btn-warning { color: #181A20; font-weight: bold; }
        .form-control, .form-control:focus { background: #23272F; color: #fff; border: 1px solid #444; }
        .alert-success { background: #2d7cff; color: #fff; border: none; }
        .alert-danger { background: #e74c3c; color: #fff; border: none; }
        .note-gold { color: #ffcb4b; font-size:0.97em; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-3">Single Student Photo Upload</h3>
        <?= $msg ?>
        <form method="post" enctype="multipart/form-data" autocomplete="off" class="mb-3">
            <div class="mb-3">
                <label for="student_id" class="form-label">Student ID</label>
                <input type="text" class="form-control" id="student_id" name="student_id"
                       required pattern="\d+" maxlength="15" placeholder="e.g. 123456"
                       value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="photo" class="form-label">Select Photo (JPG only, max 320x400px, &lt;1MB)</label>
                <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,image/jpeg" required>
            </div>
            <button type="submit" class="btn btn-warning">Upload Photo</button>
            <a href="settings.php" class="btn btn-secondary ms-3">Back to Settings</a>
        </form>
        <div class="note-gold mt-2">
            To <b>replace</b> a photo, just upload a new file with the same Student ID.<br>
            Uploaded photo will appear on the student details page if photo support is enabled.<br>
            <b>Photos are stored in <code>student_photos/</code>.</b>
        </div>
        <?php if ($success): ?>
            <div class="text-center mt-3">
                <img src="student_photos/<?= htmlspecialchars($_POST['student_id']) ?>.jpg?<?= time() ?>"
                     alt="Preview" style="max-width:160px; max-height:200px; border-radius:12px; box-shadow:0 2px 16px #181A2030;">
                <div class="note-gold mt-1">Preview: Photo as uploaded</div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
