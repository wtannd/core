<?php
/**
 * My Documents View
 * 
 * @var array $pendingDocs   — Documents with visibility >= VISIBILITY_ON_HOLD
 * @var array $announcedDocs — Paginated slice of announced documents
 * @var int   $totalAnnounced — Total count of announced docs (for heading)
 * @var int   $totalPages    — Total pages for announced group
 * @var int   $page          — Current page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - My Documents</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h1>My Documents</h1>

            <?php if (!empty($pendingDocs)): ?>
            <section class="docs-group">
                <h2>Pending Announcement (<?php echo count($pendingDocs); ?>)</h2>
                <?php $documents = $pendingDocs; include VIEWS_PATH_TRIMMED . '/partials/document_feed.php'; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($announcedDocs)): ?>
            <section class="docs-group">
                <h2>Announced (<?php echo $totalAnnounced; ?>)</h2>
                <?php $documents = $announcedDocs; include VIEWS_PATH_TRIMMED . '/partials/document_feed.php'; ?>
                <?php
                    $currentPage = $page;
                    $totalPages = $totalPages;
                    $buildPageUrl = function (int $p) { return '/mydocs?page=' . $p; };
                    include VIEWS_PATH_TRIMMED . '/partials/paginate.php';
                ?>
            </section>
            <?php endif; ?>

            <?php if (empty($pendingDocs) && empty($announcedDocs)): ?>
                <p class="text-muted">You have not authored any documents yet.</p>
            <?php endif; ?>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
