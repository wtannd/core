<?php
/**
 * 400 Error Page
 */
$pageTitle = '400 Bad Request';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <main>
        <div class="main-container auth-container">
            <h1>400 - Bad Request</h1>
            <p><?php echo htmlspecialchars($errorMessage ?? 'The request could not be understood by the server due to malformed syntax.'); ?></p>
            <br>
            <a href="/" class="btn btn-primary">Return to Homepage</a>
        </div>
    </main>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
