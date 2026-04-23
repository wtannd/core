<?php
/**
 * Reset Password View
 */
$token = $_GET['token'] ?? '';
$pageTitle = 'Reset Password';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container auth-container">
            <h1>Create New Password</h1>
            
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form action="/reset-password" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">New Password:</label>
                    <input type="password" id="password" name="password" required>
                    <small class="form-hint">At least 8 characters with uppercase, lowercase, number, and special character.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
