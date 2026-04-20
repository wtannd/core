<?php
/**
 * Registration View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Register</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container auth-container">
            <h1>Register for OpenArxiv (CORE)</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $errkey => $error): ?>
                            <li><?php echo htmlspecialchars($errkey . ": " . $error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="/register" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name (Required):</label>
                    <input type="text" id="family_name" name="family_name" required value="<?php echo htmlspecialchars($_POST['family_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email (Required):</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_email_public" name="is_email_public" value="1" <?php echo isset($_POST['is_email_public']) ? 'checked' : ''; ?>>
                        <label for="is_email_public">Make my email address public</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password (Required, min 8 chars):</label>
                    <input type="password" id="password" name="password" required 
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}"
                        title="Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <?php include VIEWS_PATH_TRIMMED . '/partials/profile_details_form.php'; ?>

                <button type="submit" class="btn btn-primary btn-login">Register</button>
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

            <p>Already have an account? <a href="/login">Login here</a>.</p>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>

    <script>
        const password = document.getElementById("password");
        const confirm_password = document.getElementById("confirm_password");

        function validatePassword(){
            if(password.value != confirm_password.value) {
                confirm_password.setCustomValidity("Passwords do not match.");
            } else {
                confirm_password.setCustomValidity('');
            }
        }

        password.onchange = validatePassword;
        confirm_password.onkeyup = validatePassword;
    </script>
<script src="/js/lookup_inst.js"></script>
</body>
</html>
