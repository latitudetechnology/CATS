<!DOCTYPE html>
<html lang="en">
<head>
    <title>Authentication Setup Help | Chromebook System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f3f6fa; }
        .help-container { max-width: 650px; margin: 40px auto; background: #fff; padding: 2rem; border-radius: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.07);}
    </style>
</head>
<body>
<div class="help-container">
    <h3 class="mb-3">Google Authentication Setup Help</h3>
    <ol>
        <li>Go to <a href="https://console.developers.google.com/" target="_blank">Google Cloud Console</a> and create a new project (or select your existing project).</li>
        <li>Go to <b>APIs &amp; Services &gt; Credentials</b>.</li>
        <li>Click <b>+ Create Credentials</b> and choose <b>OAuth client ID</b>.</li>
        <li>Configure the consent screen as prompted (choose "External" for most schools).</li>
        <li>Set application type to <b>Web application</b>.</li>
        <li>For <b>Authorized redirect URIs</b>, add:<br>
            <code>https://yourdomain.com/google_auth.php</code><br>
            (Replace with your real server address)
        </li>
        <li>After creating, copy the <b>Client ID</b> and <b>Client Secret</b> and paste them into the Authentication Settings screen.</li>
    </ol>
    <hr>
    <h5>Troubleshooting:</h5>
    <ul>
        <li>If you get a redirect URI mismatch error, double-check your <b>redirect URI</b> in Google Cloud and in your settings.</li>
        <li>If Google login fails, verify the domain is correct and accessible from your network.</li>
    </ul>
</div>
</body>
</html>
