<?php
/**
 * Registration Success View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Registration Successful</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container auth-container">
            <h1>Registration Successful!</h1>
            
            <div class="alert alert-success">
                <p>Please check your email to verify your account.</p>
                <p class="text-muted">The verification link expires in 48 hours.</p>
            </div>

            <p>Already verified? <a href="/login">Log in here</a></p>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
