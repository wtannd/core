<?php
/**
 * Document Search / Browse Results View
 * 
 * Expected variables:
 *   $pageTitle        — HTML page title
 *   $pageHeading      — Main heading text
 *   $searchQuery      — The search query string (empty for browse)
 *   $documents        — Array of document results
 *   $totalResults     — Total number of matching documents
 *   $totalPages       — Total number of pages
 *   $page             — Current page number
 *   $buildPageUrl     — Closure(int $page): string
 *   $showFilters      — Whether to show the filter bar
 *   $filterAction     — Form action URL
 *   $filters          — Current filter values array
 *   $docTypes         — (if $showFilters) DocType options
 *   $branches         — (if $showFilters) ResearchBranch options
 *   $topics           — (if $showFilters) ResearchTopic options
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - <?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="search-results-page">
            <h2><?php echo $pageHeading; ?></h2>

            <?php if ($showFilters): ?>
                <form action="<?php echo htmlspecialchars($filterAction); ?>" method="GET" class="filter-bar" id="filter-form">
                    <div class="filter-group">
                        <label for="f-type">Type</label>
                        <select name="type" id="f-type">
                            <option value="">All Types</option>
                            <?php foreach ($docTypes as $type): ?>
                                <option value="<?php echo $type['ID']; ?>" <?php echo (($filters['type'] ?? '') == $type['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['dtname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="f-branch">Branch</label>
                        <select name="branch" id="f-branch">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['bID']; ?>" <?php echo (($filters['branch'] ?? '') == $branch['bID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['abbr']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="f-topic">Topic</label>
                        <select name="topic" id="f-topic">
                            <option value="">All Topics</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?php echo $topic['tID']; ?>" <?php echo (($filters['topic'] ?? '') == $topic['tID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($topic['abbr']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="f-range">Range</label>
                        <select name="range" id="f-range">
                            <option value="">Custom</option>
                            <option value="day" <?php echo (($filters['range'] ?? '') === 'day') ? 'selected' : ''; ?>>New (24h)</option>
                            <option value="week" <?php echo (($filters['range'] ?? '') === 'week') ? 'selected' : ''; ?>>Recent (7 days)</option>
                            <option value="month" <?php echo (($filters['range'] ?? '') === 'month') ? 'selected' : ''; ?>>Month (30 days)</option>
                        </select>
                    </div>

                    <div class="filter-group filter-group-from" style="display:none;">
                        <label for="f-from">From</label>
                        <input type="date" name="from" id="f-from" value="<?php echo htmlspecialchars($filters['from'] ?? ''); ?>">
                    </div>

                    <div class="filter-group filter-group-to" style="display:none;">
                        <label for="f-to">To</label>
                        <input type="date" name="to" id="f-to" value="<?php echo htmlspecialchars($filters['to'] ?? ''); ?>">
                    </div>

                    <div class="filter-group filter-group-search">
                        <label for="f-q">Search</label>
                        <input type="text" name="q" id="f-q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Keywords...">
                    </div>

                    <button type="submit" class="btn btn-primary">Filter/Search</button>
                </form>
            <?php endif; ?>

            <div class="results-header">
                <span>
                    <?php if (!empty($searchQuery)): ?>
                        <?php echo $totalResults; ?> result<?php echo $totalResults !== 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                    <?php else: ?>
                        <?php echo $totalResults; ?> document<?php echo $totalResults !== 1 ? 's' : ''; ?> found
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($documents)): ?>
                <p class="no-results">No documents found matching your criteria.</p>
            <?php else: ?>
                <?php
                    $feedDocs = $documents;
                    include rtrim(VIEWS_PATH, '/') . '/partials/document_feed.php';
                ?>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <?php
                    $currentPage = $page;
                    include rtrim(VIEWS_PATH, '/') . '/partials/paginate.php';
                ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>

    <?php if ($showFilters): ?>
    <script>
    (function() {
        var rangeSelect = document.getElementById('f-range');
        var fromGroup = document.querySelector('.filter-group-from');
        var toGroup = document.querySelector('.filter-group-to');
        var searchInput = document.getElementById('f-q');
        var filterForm = document.getElementById('filter-form');

        function toggleDateFields() {
            var show = rangeSelect.value === '';
            fromGroup.style.display = show ? '' : 'none';
            toGroup.style.display = show ? '' : 'none';
        }

        function toggleFormAction() {
            filterForm.action = searchInput.value.trim() !== '' ? '/match' : '/browse';
        }

        rangeSelect.addEventListener('change', toggleDateFields);
        searchInput.addEventListener('input', toggleFormAction);

        // Init on load
        toggleDateFields();
        toggleFormAction();
    })();
    </script>
    <?php endif; ?>
</body>
</html>
