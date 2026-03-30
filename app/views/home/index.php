<?php
/**
 * Main Dashboard View
 */
$isLoggedIn = isset($_SESSION['mID']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="dashboard-wrapper">
            
            <div class="intro-block">
                <?php if ($isLoggedIn): ?>
                    Welcome back to <?php echo SITE_TITLE; ?>. Explore the latest original research, publish your own work, evaluate peer contributions, and track your Achievement Level (AL) and Earned Credit Points (ECP) to advance within your community.
                <?php else: ?>
                    <?php echo SITE_TITLE; ?> is a Community-driven Open Research Ecosystem designed to revolutionize how basic research is shared, evaluated, and rewarded. By replacing outdated publishing, funding, and hiring models with a properly incentivized feedback system, CORE puts the power back in the hands of researchers. Our rigorous, community-governed metrics ensure your work is judged by its true quality, not sheer quantity. Join us to build a self-sustaining future for scientific advancement—explore the project on (<a href="https://github.com/wtannd/core" target="_blank" rel="noopener noreferrer">GitHub</a>).
                <?php endif; ?>
            </div>

            <form action="/browse" method="GET" class="search-browse-block">
                <select name="type">
                    <?php foreach ($docTypes as $type): ?>
                        <option value="<?php echo $type['ID']; ?>" <?php echo $type['ID'] == 1 ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['dtname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="branch">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['bID']; ?>">
                            <?php echo htmlspecialchars($branch['abbr']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="btn-group">
                    <button type="submit" name="range" value="day" class="btn-mini">New</button>
                    <button type="submit" name="range" value="week" class="btn-mini">Recent</button>
                    <button type="submit" class="btn-mini">Browse</button>
                </div>
            </form>

            <?php if ($isLoggedIn && (!empty($userWorkAreas) || !empty($userInterestAreas))): ?>
                <div class="user-areas-section">
                    <?php if (!empty($userWorkAreas)): ?>
                        <h3>Your Work Areas</h3>
                        <div class="area-grid">
                            <?php foreach ($userWorkAreas as $area): ?>
                                <div class="area-card">
                                    <span class="area-abbr" title="<?php echo htmlspecialchars($area['label']); ?>"><?php echo htmlspecialchars($area['abbr']); ?></span>
                                    <div class="area-card-actions">
                                        <a href="/browse?branch=<?php echo $area['bID']; ?>&range=day" class="btn-mini btn-small">New</a>
                                        <a href="/browse?branch=<?php echo $area['bID']; ?>&range=week" class="btn-mini btn-small">Recent</a>
                                        <form action="/match" method="GET" class="area-search-form">
                                            <input type="hidden" name="branch" value="<?php echo $area['bID']; ?>">
                                            <input type="text" name="q" placeholder="Search..." class="area-search-input">
                                            <button type="submit" class="btn-mini btn-small">Search</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($userInterestAreas)): ?>
                        <h3 style="margin-top: 2rem;">Your Interest Areas</h3>
                        <div class="area-grid">
                            <?php foreach ($userInterestAreas as $area): ?>
                                <div class="area-card">
                                    <span class="area-abbr" title="<?php echo htmlspecialchars($area['label']); ?>"><?php echo htmlspecialchars($area['abbr']); ?></span>
                                    <div class="area-card-actions">
                                        <a href="/browse?branch=<?php echo $area['bID']; ?>&range=day" class="btn-mini btn-small">New</a>
                                        <a href="/browse?branch=<?php echo $area['bID']; ?>&range=week" class="btn-mini btn-small">Recent</a>
                                        <form action="/match" method="GET" class="area-search-form">
                                            <input type="hidden" name="branch" value="<?php echo $area['bID']; ?>">
                                            <input type="text" name="q" placeholder="Search..." class="area-search-input">
                                            <button type="submit" class="btn-mini btn-small">Search</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <section class="recent-feed">
                <h2>Recent Original Research</h2>
                <?php 
                $documents = $recentDocs;
                include __DIR__ . '/../partials/document_feed.php'; 
                ?>
            </section>

        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
