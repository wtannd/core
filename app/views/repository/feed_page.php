<?php
/**
 * Feed page
 */
$pageTitle = 'Recent ePrints';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
    <script src="/js/load_mathjax.js" async></script>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h2>Recent ePrints</h2>
            <?php include VIEWS_PATH_TRIMMED . '/partials/document_feed.php'; ?>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
