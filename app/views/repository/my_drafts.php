<?php
/**
 * My Drafts View
 * 
 * @var array $drafts — DocDrafts rows for the current user
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - My Drafts</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="my-drafts-container">
            <h1>My Drafts</h1>

            <?php if (!empty($drafts)): ?>
                <?php $documents = $drafts; include rtrim(VIEWS_PATH, '/') . '/partials/draft_feed.php'; ?>
            <?php else: ?>
                <p class="text-muted">You have no drafts yet. <a href="/upload">Upload a document</a> to get started.</p>
            <?php endif; ?>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
