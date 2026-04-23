<?php
/**
 * General Error Page
 */
$pageTitle = 'Error';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <main>
        <div class="main-container auth-container">
            <h1>An Error Occurred</h1>
            <p><?php echo htmlspecialchars($errorMessage ?? 'An unexpected error has occurred. Please try again later.'); ?></p>
            <br>
            <a href="/" class="btn btn-primary">Return to Homepage</a>
        </div>
    </main>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
