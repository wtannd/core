<?php

use app\models\Draft;

/**
 * Document Viewer View (Draft)
 * 
 * Expected $docData keys:
 *   'doc'       — Draft entity
 *   'draftAuthors'   — DocDraftAuthors rows
 *   'isFullyApproved'— bool
 *   'branches'       — bIDs from branch_list JSON + ResearchBranches
 *   'topic'          — from ResearchTopics by tID, or false
 *   'extLinks'       — parsed from link_list JSON
 *   'isSubmitter'    — to submit or edit
 */
$mID = (int)($_SESSION['mID'] ?? 0);

$userApprovalNeeded = false;
foreach ($draftAuthors as $da) {
    if ((int)($da['mID'] ?? 0) === $mID && (int)($da['approved'] ?? 0) === 0) {
        $userApprovalNeeded = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - [Draft] <?php echo htmlspecialchars($doc->title ?: '[Untitled]'); ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <!-- Draft Tag -->
            <div class="draft-tag">DRAFT MODE</div>

            <!-- Branch/Topic Labels -->
            <?php if (!empty($branches) || $topic): ?>
            <div class="doc-labels">
                <?php foreach ($branches as $b): ?>
                    <a href="/browse?branch=<?php echo $b['bID']; ?>&range=week" class="label-pill label-branch" title="<?php echo htmlspecialchars($b['bname']); ?>">
                        <?php echo htmlspecialchars($b['abbr']); ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($topic): ?>
                    <a href="/browse?topic=<?php echo $topic['tID']; ?>&range=week" class="label-pill label-topic" title="<?php echo htmlspecialchars($topic['tname']); ?>">
                        <?php echo htmlspecialchars($topic['abbr']); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Title -->
            <h1 class="doc-title"><?php echo htmlspecialchars($doc->title ?: '[Untitled Draft]'); ?></h1>

            <!-- Two-Column Layout -->
            <div class="doc-body">
                <!-- Left: Major Content -->
                <div class="doc-main">
                    <!-- Authors -->
                    <div class="author-section">
                        <div class="author-names">
                            <?php
                            $authorLinks = [];
                            foreach ($doc->getAuthors() as $author) {
                                $name = htmlspecialchars($author[0]);
                                $duty = (int)$author[2];
                                $affilRefs = !empty($author[3]) ? '<sup>' . htmlspecialchars(implode(',', $author[3])) . '</sup>' : '';
                                $authorLinks[] = "<strong>{$name}</strong>{$affilRefs} ({$duty}%)";
                            }

                            $visibleAuthors = array_slice($authorLinks, 0, 10);
                            $hiddenAuthors = array_slice($authorLinks, 10);

                            echo implode(', ', $visibleAuthors);

                            if (!empty($hiddenAuthors)): ?>
                                <span id="hidden-authors" style="display: none;">, <?php echo implode(', ', $hiddenAuthors); ?></span>
                                <a href="javascript:void(0)" onclick="this.style.display='none'; document.getElementById('hidden-authors').style.display='inline';">[Show more authors]</a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($doc->getAffiliations())): ?>
                            <ul class="affiliation-list">
                                <?php
                                $visibleAffiliations = array_slice($doc->getAffiliations(), 0, 3);
                                $hiddenAffiliations = array_slice($doc->getAffiliations(), 3);

                                foreach ($visibleAffiliations as $affil): ?>
                                    <li><?php echo htmlspecialchars((string)$affil[0]) . '. ' . htmlspecialchars($affil[1]); ?></li>
                                <?php endforeach; ?>

                                <?php if (!empty($hiddenAffiliations)): ?>
                                    <?php foreach ($hiddenAffiliations as $affil): ?>
                                        <li class="hidden-affil" style="display: none;"><?php echo htmlspecialchars((string)$affil[0]) . '. ' . htmlspecialchars($affil[1]); ?></li>
                                    <?php endforeach; ?>
                                    <li id="show-more-affils"><a href="javascript:void(0)" onclick="document.querySelectorAll('.hidden-affil').forEach(el => el.style.display='list-item'); this.parentElement.style.display='none';">[Show more affiliations]</a></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($doc->notes)): ?>
                        <div class="doc-notes"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($doc->notes)); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Right: Sidebar -->
                <div class="doc-sidebar">
                    <!-- Draft Saved -->
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Draft Created</div>
                        <div class="doc-sidebar-value"><?php echo $doc->getFormattedDatetimeAdded(); ?></div>
                    </div>

                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Last Updated</div>
                        <div class="doc-sidebar-value"><?php echo $doc->getFormattedLastUpdateTime(); ?></div>
                    </div>

                    <?php if (!empty($doc->pub_date)): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Date Published</div>
                        <div class="doc-sidebar-value"><?php echo $doc->getFormattedPubDate(); ?></div>
                    </div>
                    <?php endif; ?>

                    <hr class="doc-sidebar-divider">

                    <!-- Full PDF -->
                    <?php if ($doc->hasMainFile()): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Full Text PDF</div>
                        <a href="<?php echo $doc->getMainFileLink(); ?>" class="doc-file-link" download>Download</a>
                    </div>
                    <?php endif; ?>

                    <!-- Supplemental File -->
                    <?php if ($doc->hasSupplFile()): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Supplemental File</div>
                        <a href="<?php echo $doc->getSupplFileLink(); ?>" class="doc-file-link" download>Download</a>
                    </div>
                    <?php endif; ?>

                    <!-- External Links -->
                    <?php if (!empty($extLinks)): ?>
                    <div class="doc-sidebar-section">
                        <details>
                            <summary>External Links</summary>
                            <div>
                                <?php foreach ($extLinks as $link):
                                    $sID = (int)($link[0] ?? 0);
                                    $sourceName = $link[1] ?? '';
                                    $url = $link[2] ?? '';
                                    $href = (strpos($url, 'http') === 0) ? $url : 'https://doi.org/' . $url;
                                ?>
                                    <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener noreferrer" class="doc-ext-link">
                                        <?php echo htmlspecialchars($sourceName); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>
                    <?php endif; ?>


                </div>
            </div>

            <!-- Approval Status & Actions (full width) -->
            <div class="action-zone">
                <h3>Approval Status</h3>
                <table class="approval-table">
                    <thead>
                        <tr>
                            <th>Author</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($draftAuthors as $da): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($da['display_name'] ?? 'Author'); ?></td>
                                <td><?php echo $da['mID'] ? 'Member' : 'External'; ?></td>
                                <td>
                                    <?php if ($da['approved']): ?>
                                        <span class="status-approved">&#10003; Approved</span>
                                    <?php else: ?>
                                        <span class="status-pending">&#9710; Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($userApprovalNeeded): ?>
                    <form action="/draft/approve" method="POST" class="approval-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="dID" value="<?php echo $doc->dID; ?>">
                        <button type="submit" class="btn btn-submit">Approve this Draft</button>
                    </form>
                <?php endif; ?>

                <?php if ($isSubmitter): ?>
                    <div class="action-row">
                        <form action="/draft/finalize" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="dID" value="<?php echo $doc->dID; ?>">
                            <button type="submit" class="btn btn-submit" <?php echo !$isFullyApproved ? 'disabled' : ''; ?>>
                                Finalize Submission
                            </button>
                        </form>
                        <?php if (!$isFullyApproved): ?>
                            <small class="text-muted">Waiting for all co-authors to approve.</small>
                        <?php endif; ?>

                        <a href="<?php echo $doc->getEditUrl(); ?>" class="btn btn-secondary" onclick="return confirm('Editing this draft will unlock it and reset all current co-author approvals. Continue?');">
                            Edit Draft
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabs -->
            <div class="doc-tabs">
                <div class="doc-tab-left">
                    <button class="doc-tab active" data-panel="panel-abstract">Abstract</button>
                    <button class="doc-tab" data-panel="panel-details">Details</button>
                </div>
                <?php if ($doc->hasMainFile()): ?>
                <div class="doc-tab-right">
                    <button class="doc-tab" data-panel="panel-pdf">Preview PDF</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Abstract Panel -->
            <div id="panel-abstract" class="doc-tab-panel active">
                <div class="doc-abstract">
                    <?php echo nl2br(htmlspecialchars($doc->abstract ?: '[No abstract provided]')); ?>
                </div>
            </div>

            <!-- Details Panel -->
            <div id="panel-details" class="doc-tab-panel">
                <table class="revision-table">
                    <thead>
                        <tr><th>Field</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Draft ID</td><td><?php echo $doc->dID; ?></td></tr>
                        <tr><td>Document Type</td><td><?php echo $doc->dtype; ?></td></tr>
                        <tr><td>Created</td><td><?php echo $doc->getFormattedDatetimeAdded(); ?></td></tr>
                        <tr><td>Last Updated</td><td><?php echo $doc->getFormattedLastUpdateTime(); ?></td></tr>
                        <?php if (!empty($doc->pub_date)): ?>
                        <tr><td>Date Published/Posted</td><td><?php echo $doc->getFormattedPubDate(); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($doc->recv_date)): ?>
                        <tr><td>Date Received</td><td><?php echo $doc->getFormattedRecvDate(); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>File(s)</td><td><?php echo $doc->getFileTypeLabel(); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- PDF Preview Panel -->
            <?php if ($doc->hasMainFile()): ?>
            <div id="panel-pdf" class="doc-tab-panel">
                <object data="<?php echo $doc->getMainFileLink(); ?>"
                        type="application/pdf"
                        width="100%"
                        height="800px"
                        class="pdf-frame">
                    <p>
                        Your browser does not support inline PDF viewing.
                        <a href="<?php echo $doc->getMainFileLink(); ?>">Download the PDF</a> to view it.
                    </p>
                </object>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>

    <script>
    (function() {
        document.querySelectorAll('.doc-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.doc-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.doc-tab-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(this.dataset.panel).classList.add('active');
            });
        });
    })();
    </script>
</body>
</html>
