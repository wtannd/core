<?php
/**
 * Login View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Login</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="auth-container">
            <h1>Login to OpenArxiv (CORE)</h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-info">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="/login" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me" <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                    <label for="remember_me">Remember me on this device</label>
                </div>

                <button type="submit" class="btn btn-primary btn-login">Login</button>
            </form>

            <hr>
            
            <a href="/orcid_login" class="btn btn-orcid">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 256 256" xml:space="preserve">
                    <style type="text/css">.st0{fill:#A6CE39;}.st1{fill:#FFFFFF;}</style>
                    <path class="st0" d="M256,128c0,70.7-57.3,128-128,128C57.3,256,0,198.7,0,128C0,57.3,57.3,0,128,0C198.7,0,256,57.3,256,128z"/>
                    <g>
                        <path class="st1" d="M86.3,186.2H70.9V79.1h15.4V186.2z"/>
                        <path class="st1" d="M108.9,79.1h36.6c33.6,0,52.8,23.3,52.8,53.5c0,31.6-19.1,53.5-53.1,53.5h-36.3V79.1z M124.3,172.4h24.5 c24.1,0,38.2-16.1,38.2-40c0-22.5-13.1-39.5-38.6-39.5h-24.1V172.4z"/>
                        <path class="st1" d="M128,79.1c5.1,0,9.2-4.1,9.2-9.2c0-5.1-4.1-9.2-9.2-9.2c-5.1,0-9.2,4.1-9.2,9.2C118.8,75,122.9,79.1,128,79.1z"/>
                    </g>
                </svg>
                Sign in with ORCID
            </a>

            <p>Don't have an account? <a href="/register">Register here</a>.</p>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
