<?php
/**
 * Member Search Results View
 * 
 * Expected variables:
 *   $query           — The search query string
 *   $members         — Array of member results
 *   $totalResults    — Total number of matching members
 *   $totalPages      — Total number of pages
 *   $page            — Current page number
 *   $buildPageUrl    — Closure(int $page): string
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Member Search</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h2>Member Search</h2>

            <form action="/findmembers" method="GET" class="filter-bar">
                <div class="filter-group filter-group-search">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search members by name..." class="search-input">
                </div>
                <button type="submit" class="btn btn-primary btn-small">Search</button>
            </form>

            <div class="results-header">
                <span>
                    <?php echo $totalResults; ?> member<?php echo $totalResults !== 1 ? 's' : ''; ?> found
                    <?php if (!empty($query)): ?>
                        for "<strong><?php echo htmlspecialchars($query); ?></strong>"
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($members)): ?>
                <p class="no-results">No members found matching your search.</p>
            <?php else: ?>
                <div class="member-list">
                    <?php foreach ($members as $member): ?>
                        <?php
                            $rawId = $member['CoreID'] ?? '';
                            $paddedId = str_pad(strtoupper(trim($rawId)), 9, '0', STR_PAD_LEFT);
                            $formattedId = substr($paddedId, 0, 3) . '-' . substr($paddedId, 3, 3) . '-' . substr($paddedId, 6, 3);
                        ?>
                        <div class="member-item">
                            <a href="/member/<?php echo htmlspecialchars($rawId); ?>" class="member-name">
                                <?php echo htmlspecialchars($member['display_name']); ?>
                            </a>
                            <span class="member-core-id"><?php echo $formattedId; ?></span>
                            <?php if (!empty($member['iname'])): ?>
                                <span class="member-institution"><?php echo htmlspecialchars($member['iname']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <?php
                    $currentPage = $page;
                    include VIEWS_PATH_TRIMMED . '/partials/paginate.php';
                ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
