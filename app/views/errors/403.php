<?php
/**
 * 403 Forbidden Page
 */
$pageTitle = '403 Forbidden';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
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
