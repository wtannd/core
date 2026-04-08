<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - 403 Forbidden</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <main>
        <div class="main-container auth-container">
            <h1>403 - Forbidden</h1>
            <p>Sorry, you do not have permission to access this resource, which may not exist, or your session has expired (CSRF failure) - Please refresh the page and try again.</p>
            <br>
            <a href="/" class="btn btn-primary">Return to Homepage</a>
        </div>
    </main>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
