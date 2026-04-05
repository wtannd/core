<?php
// public/install.php
session_start();

// 1. Security Check: If config exists, abort to prevent overwriting/hacking
if (file_exists(__DIR__ . '/../config/config.php')) {
    die("Application is already installed. Please delete install.php for security.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? '';
    $adminPass = $_POST['admin_pass'] ?? '';
    $maxMB = (int)$_POST['max_mb'] ?? 0;
    $siteName = $_POST['site_name'] ?? '';
    $siteURL = $_POST['site_url'] ?? '';
    $siteEmail = $_POST['site_email'] ?? '';
    $subEmail = $_POST['sub_email'] ?? '';
    $orcid = $_POST['orcid'] ?? '';
    $orcidSecret = $_POST['orcid_secret'] ?? '';
    $cronSecret = $_POST['cron_secret'] ?? '';

    try {
        // Step 1: Test Database Connection
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Step 2: Run Database Schema
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new Exception("schema.sql not found!");
        }
        $sql = file_get_contents($schemaPath);
        $pdo->exec($sql);

        // Step 3: Create Admin User
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Members (mID, email, password_hash, admin_role, mrole, family_name) VALUES (1, ?, ?, 999, 99, 'Admin')");
        $stmt->execute([$adminEmail, $hash]);

        // Step 4: Generate config.php
        $configTemplate = file_get_contents(__DIR__ . '/../config/config.sample.php');
        $newConfig = str_replace(
            ['{{DB_HOST}}', '{{DB_NAME}}', '{{DB_USER}}', '{{DB_PASS}}', {{MAX_MB}}, '{{SITE_NAME}}', '{{SITE_URL}}', '{{SITE_EMAIL}}', '{{SUBMISSION_EMAIL}}', '{{ORCID_CLIENT_ID}}', '{{ORCID_CLIENT_SECRET}}', '{{CRON_SECRET_TOKEN}}'],
            [$dbHost, $dbName, $dbUser, $dbPass, $maxMB, $siteName, $siteURL, $siteEmail, $subEmail, $orcid, $orcidSecret, $cronSecret],
            $configTemplate
        );
        file_put_contents(__DIR__ . '/../config/config.php', $newConfig);

        $success = "Installation complete! Please delete <b>public/install.php</b> immediately, then <a href='/login'>login here</a>.";

    } catch (Exception $e) {
        $error = "Installation failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CORE System Setup</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f8; display: flex; justify-content: center; padding-top: 50px; }
        .setup-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 400px; }
        input { width: 100%; padding: 8px; margin-bottom: 15px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #2C5E4E; color: white; border: none; cursor: pointer; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="setup-box">
        <h2>CORE Setup</h2>
        
        <?php if ($error) echo "<div class='error'>$error</div>"; ?>
        <?php if ($success): ?>
            <div class='success'><?= $success ?></div>
        <?php else: ?>
            <form method="POST">
                <h3>Database Details</h3>
                <label>Host</label>
                <input type="text" name="db_host" value="localhost" required>
                <label>Database Name</label>
                <input type="text" name="db_name" required>
                <label>Username</label>
                <input type="text" name="db_user" required>
                <label>Password</label>
                <input type="password" name="db_pass">

                <h3>Admin Account</h3>
                <label>Admin Email</label>
                <input type="email" name="admin_email" required>
                <label>Admin Password</label>
                <input type="password" name="admin_pass" required>

                <h3>Admin Account</h3>
                <label>Max Upload Size in MB</label>
                <input type="number" name="max_mb" required>
                <label>Site Name</label>
                <input type="text" name="site_name" required>
                <label>Site URL</label>
                <input type="text" name="site_url" required>
                <label>Site Contact Email</label>
                <input type="email" name="site_email" required>
                <label>Email for Document Submission</label>
                <input type="email" name="sub_email" required>
                <label>ORCID Client ID</label>
                <input type="text" name="orcid" required>
                <label>ORCID CLIENT SECRET</label>
                <input type="text" name="orcid_secret" required>
                <label>CRON SECRET TOKEN (a hash string to start cron by /cron?token=xxx)</label>
                <input type="text" name="cron_secret" required>

                <button type="submit">Install Application</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
