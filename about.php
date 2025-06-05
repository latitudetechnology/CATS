<?php
// about.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About | CATS - Chromebook Asset Tracking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #EDEDED; }
        .container { max-width: 680px; margin-top: 3em; }
        .about-card { background: #23272F; border: none; box-shadow: 0 4px 18px #10121a22; padding: 2.1em 2em 1.6em; border-radius: 1.5em;}
        h2, h3, h4, .about-card strong { color: #ffcb4b; }
        ul { margin-left: 1.2em; }
        .about-version { color: #888; font-size:1.1em; }
        .changelog li strong { color: #ffcb4b; }
    </style>
</head>
<body>
<div class="container">
    <div class="about-card">
        <h2>CATS - Chromebook Asset Tracking System</h2>
        <p class="about-version">
            Version 2.0 &mdash; 2025<br>
            (Original 1.0 released September 2017)
        </p>
        <p>
            <strong>CATS</strong> was created to solve the real-world headaches of tracking Chromebooks and student devices in a 1:1 environment. 
            <br><br>
            When our district first rolled out 1:1 in 2014, managing spreadsheets, serial numbers, assignments, and repairs quickly became unsustainable. That led to developing a custom system tailored for real K-12 needs.
        </p>
        <ul>
            <li>Nightly, automated student data imports</li>
            <li>One-click workorder tracking &amp; device repair status</li>
            <li>Loaner device management and checkout/check-in automation</li>
            <li>Device location, assignment, and charger tracking</li>
            <li>Quick web-based reporting &amp; stats, including touch-friendly features</li>
            <li>Designed for tech directors, secretaries, librarians, and support staff</li>
        </ul>
        <p>
            <strong>Built by:</strong> Latitude Technology<br>
            <span style="color:#ccc;">(All rights reserved. Copyright &copy; Latitude Technology 2025)</span>
        </p>
        <p>
            <strong>Technology:</strong> PHP 8+, MySQL/MariaDB, Bootstrap 5, Chart.js
        </p>
        <hr>
        <h4>Changelog</h4>
        <ul class="changelog">
            <li><strong>2025 - Version 2.0:</strong> Major rewrite with modern UI, improved security, streamlined upgrades, and new reporting features.</li>
            <li><strong>2017 - Version 1.0:</strong> First web-based edition replaces spreadsheet tracking for student device management.</li>
        </ul>
        <hr>
        <div style="font-size:1em;color:#aaa;">
            <!-- Optional logo or future snazzy name can go here -->
            <?= date('Y') ?> &copy; Latitude Technology. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
