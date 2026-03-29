<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - 400 Bad Request</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>
    <main>
        <div class="auth-container">
            <h1>400 - Bad Request</h1>
            <p><?php echo htmlspecialchars($errorMessage ?? 'The request could not be understood by the server due to malformed syntax.'); ?></p>
            <br>
            <a href="/" class="btn btn-primary">Return to Homepage</a>
        </div>
    </main>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
