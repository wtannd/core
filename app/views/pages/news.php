<?php
/**
 * News Page
 */
$pageTitle = $pageTitle ?? 'Announcements';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <div class="static-container mt-4 about-page">
	    <header class="mb-5">
			<h2 class="display-4"><?php echo htmlspecialchars($pageTitle); ?></h2>
			<p class="lead">
			</p>
		</header>
        <section class="mb-5">
            <?php if (empty($newsList)): ?>
                <p>No Announcements.</p>
            <?php else: ?>
                <?php $newsFeed = $newsList; include VIEWS_PATH_TRIMMED . '/partials/news_feed.php'; ?>
            <?php endif; ?>
        </section>
    </div>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
