<?php
/**
 * Forgot Password View
 */
$pageTitle = 'Forgot Password';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container auth-container">
            <h1>Reset Your Password</h1>
            
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form action="/forgot-password" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </form>

            <p><a href="/login">Back to Login</a></p>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
