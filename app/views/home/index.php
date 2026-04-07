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
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="dashboard-wrapper">
            
            <?php if (isset($_SESSION['warning_message'])): ?>
                <div class="alert alert-warning">
                    <?php echo htmlspecialchars($_SESSION['warning_message']); unset($_SESSION['warning_message']); ?>
                </div>
            <?php endif; ?>

            <div class="intro-block">
                <?php if ($isLoggedIn): ?>
                    Welcome back to <?php echo SITE_TITLE; ?>. Explore the latest original research, publish your own work, evaluate peer contributions, and track your Achievement Level (AL) and Earned Credit Points (ECP) to advance within your community.
                <?php else: ?>
                    <?php echo SITE_TITLE; ?> is a Community-driven Open Research Ecosystem designed to revolutionize how basic research is shared, evaluated, and rewarded. By replacing outdated publishing, funding, and hiring models with a properly incentivized feedback system, CORE puts the power back in the hands of researchers. Our rigorous, community-governed metrics ensure your work is judged by its true quality, not sheer quantity. Join us to build a self-sustaining future for scientific advancement—explore the project on (<a href="https://github.com/wtannd/core" target="_blank" rel="noopener noreferrer">GitHub</a>).
                <?php endif; ?>
            </div>

            <form action="/browse" method="GET" class="filter-bar">
                <div class="filter-group">
                    <label for="f-type">Type</label>
                    <select name="type" id="f-type">
                        <?php foreach ($docTypes as $type): ?>
                            <option value="<?php echo $type['ID']; ?>" <?php echo $type['ID'] == 1 ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $branch['bID']; ?>">
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
                            <option value="<?php echo $topic['tID']; ?>">
                                <?php echo htmlspecialchars($topic['abbr']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group filter-group-range">
                    <label>Range</label>
                    <div class="range-btns">
                        <button type="submit" name="range" value="day" class="btn btn-mini">New</button>
                        <button type="submit" name="range" value="week" class="btn btn-mini">Recent</button>
                        <button type="submit" class="btn btn-mini">Browse</button>
                    </div>
                </div>
            </form>

            <?php if ($isLoggedIn && (!empty($userWorkAreas) || !empty($userInterestAreas))): ?>
                <div class="user-areas-section">
                    <div class="areas-tabs">
                        <?php if (!empty($userWorkAreas)): ?>
                            <button class="areas-tab active" data-tab="work">Work Areas</button>
                        <?php endif; ?>
                        <?php if (!empty($userInterestAreas)): ?>
                            <button class="areas-tab <?php echo empty($userWorkAreas) ? 'active' : ''; ?>" data-tab="interest">Interest Areas</button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($userWorkAreas)): ?>
                    <div class="areas-tab-panel active" id="panel-work">
                        <div class="area-pill-grid">
                            <?php foreach ($userWorkAreas as $area): ?>
                            <div class="area-pill-row">
                                <a href="/browse?branch=<?php echo $area['bID']; ?>&range=week" class="label-pill label-branch" title="<?php echo htmlspecialchars($area['label']); ?>">
                                    <?php echo htmlspecialchars($area['abbr']); ?>
                                </a>
                                <a href="/browse?branch=<?php echo $area['bID']; ?>&range=day" class="btn btn-mini btn-small" title="New documents in this branch">New</a>
                                <form action="/match" method="GET" class="area-pill-search">
                                    <input type="hidden" name="branch" value="<?php echo $area['bID']; ?>">
                                    <input type="text" name="q" placeholder="Search..." class="area-pill-input">
                                    <button type="submit" class="btn btn-mini btn-small">Go</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($userInterestAreas)): ?>
                    <div class="areas-tab-panel <?php echo empty($userWorkAreas) ? 'active' : ''; ?>" id="panel-interest">
                        <div class="area-pill-grid">
                            <?php foreach ($userInterestAreas as $area): ?>
                            <div class="area-pill-row">
                                <a href="/browse?branch=<?php echo $area['bID']; ?>&range=week" class="label-pill label-branch" title="<?php echo htmlspecialchars($area['label']); ?>">
                                    <?php echo htmlspecialchars($area['abbr']); ?>
                                </a>
                                <a href="/browse?branch=<?php echo $area['bID']; ?>&range=day" class="btn btn-mini btn-small" title="New documents in this branch">New</a>
                                <form action="/match" method="GET" class="area-pill-search">
                                    <input type="hidden" name="branch" value="<?php echo $area['bID']; ?>">
                                    <input type="text" name="q" placeholder="Search..." class="area-pill-input">
                                    <button type="submit" class="btn btn-mini btn-small">Go</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <section class="recent-feed">
                <h2>Recent Original Research</h2>
                <?php 
                $documents = $recentDocs;
                include VIEWS_PATH_TRIMMED . '/partials/document_feed.php'; 
                ?>
            </section>

        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>

    <script>
    (function() {
        document.querySelectorAll('.areas-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var section = this.closest('.user-areas-section');
                section.querySelectorAll('.areas-tab').forEach(function(t) { t.classList.remove('active'); });
                section.querySelectorAll('.areas-tab-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById('panel-' + this.dataset.tab).classList.add('active');
            });
        });
    })();
    </script>
</body>
</html>
