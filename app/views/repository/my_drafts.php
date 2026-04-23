<?php
/**
 * My Drafts View
 * 
 * @var array $drafts — DocDrafts rows for the current user
 */
$pageTitle = 'My Drafts';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
    <script src="/js/load_mathjax.js" async></script>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h1>My Drafts</h1>

            <?php if (!empty($drafts)): ?>
                <?php $documents = $drafts; include VIEWS_PATH_TRIMMED . '/partials/draft_feed.php'; ?>
            <?php else: ?>
                <p class="text-muted">You have no drafts yet. <a href="/upload">Upload a document</a> to get started.</p>
            <?php endif; ?>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
