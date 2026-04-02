<?php
/**
 * Document Viewer View (Draft)
 * 
 * Expected $docData keys:
 *   'document'       — DocDrafts row array
 *   'draftAuthors'   — DocDraftAuthors rows
 *   'isFullyApproved'— bool
 *   'branches'       — parsed from branch_list JSON
 *   'topic'          — from ResearchTopics by tID, or false
 *   'extLinks'       — parsed from link_list JSON
 */
$doc = $docData['document'];
$authorData = json_decode($doc['author_list'], true) ?? [];
$authors = $authorData['authors'] ?? [];
$affiliations = $authorData['affiliations'] ?? [];
$branches = $docData['branches'] ?? [];
$topic = $docData['topic'] ?? false;
$extLinks = $docData['extLinks'] ?? [];

$draftAuthors = $docData['draftAuthors'] ?? [];
$isFullyApproved = $docData['isFullyApproved'] ?? true;
$mID = (int)($_SESSION['mID'] ?? 0);
$isSubmitter = (int)$doc['submitter_ID'] === $mID;

$hasFileVal = (int)($doc['has_file'] ?? 0);
$hasMainFile = $hasFileVal > 0;
$hasSupplFile = ($hasFileVal === 2 || $hasFileVal === 3);

// Check if user needs to approve
$userApprovalNeeded = false;
foreach ($draftAuthors as $da) {
    if ((int)$da['mID'] === $mID && (int)$da['approved'] === 0) {
        $userApprovalNeeded = true;
        break;
    }
}

// Stream type prefix for drafts
$streamPrefix = 'draft';
$streamSupplPrefix = 'draft';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - [Draft] <?php echo htmlspecialchars($doc['title'] ?: '[Untitled]'); ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="document-viewer">
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
            <h1 class="doc-title"><?php echo htmlspecialchars($doc['title'] ?: '[Untitled Draft]'); ?></h1>

            <!-- Two-Column Layout -->
            <div class="doc-body">
                <!-- Left: Major Content -->
                <div class="doc-main">
                    <!-- Authors -->
                    <div class="author-section">
                        <div class="author-names">
                            <?php
                            $authorLinks = [];
                            foreach ($authors as $author) {
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

                        <?php if (!empty($affiliations)): ?>
                            <ul class="affiliation-list">
                                <?php
                                $visibleAffiliations = array_slice($affiliations, 0, 3);
                                $hiddenAffiliations = array_slice($affiliations, 3);

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
                    <?php if (!empty($doc['notes'])): ?>
                        <div class="doc-notes"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($doc['notes'])); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Right: Sidebar -->
                <div class="doc-sidebar">
                    <!-- Draft Saved -->
                    <div class="doc-sidebar-section">
                        <div class="doc-sidebar-label">Draft Saved</div>
                        <div class="doc-sidebar-value"><?php echo date('M d, Y H:i', strtotime($doc['datetime_added'])); ?> UTC</div>
                    </div>

                    <?php if (!empty($doc['pubdate'])): ?>
                    <div class="doc-sidebar-section">
                        <div class="doc-sidebar-label">Pub Date</div>
                        <div class="doc-sidebar-value"><?php echo htmlspecialchars($doc['pubdate']); ?></div>
                    </div>
                    <?php endif; ?>

                    <hr class="doc-sidebar-divider">

                    <!-- Full PDF -->
                    <?php if ($hasMainFile): ?>
                    <div class="doc-sidebar-section">
                        <div class="doc-sidebar-label">Full Text PDF</div>
                        <a href="/stream?type=draft&id=<?php echo $doc['dID']; ?>" class="doc-file-link" download>Download</a>
                    </div>
                    <?php endif; ?>

                    <!-- Supplemental File -->
                    <?php if ($hasSupplFile): ?>
                    <div class="doc-sidebar-section">
                        <div class="doc-sidebar-label">Supplemental File</div>
                        <a href="/stream?type=draft&id=<?php echo $doc['dID']; ?>&suppl" class="doc-file-link" download>Download</a>
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
                        <input type="hidden" name="dID" value="<?php echo $doc['dID']; ?>">
                        <button type="submit" class="btn btn-primary btn-approve">Approve this Draft</button>
                    </form>
                <?php endif; ?>

                <?php if ($isSubmitter): ?>
                    <div class="finalize-actions">
                        <form action="/draft/finalize" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="dID" value="<?php echo $doc['dID']; ?>">
                            <button type="submit" class="btn btn-submit <?php echo !$isFullyApproved ? 'btn-disabled' : ''; ?>" <?php echo !$isFullyApproved ? 'disabled' : ''; ?>>
                                Finalize Submission
                            </button>
                        </form>
                        <?php if (!$isFullyApproved): ?>
                            <small class="approval-note">Waiting for all co-authors to approve.</small>
                        <?php endif; ?>

                        <a href="/draft/edit?id=<?php echo $doc['dID']; ?>" class="btn btn-edit-draft" onclick="return confirm('Editing this draft will unlock it and reset all current co-author approvals. Continue?');">
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
                <?php if ($hasMainFile): ?>
                <div class="doc-tab-right">
                    <button class="doc-tab" data-panel="panel-pdf">Preview PDF</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Abstract Panel -->
            <div id="panel-abstract" class="doc-tab-panel active">
                <div class="doc-abstract">
                    <?php echo nl2br(htmlspecialchars($doc['abstract'] ?: '[No abstract provided]')); ?>
                </div>
            </div>

            <!-- Details Panel -->
            <div id="panel-details" class="doc-tab-panel">
                <table class="revision-table">
                    <thead>
                        <tr><th>Field</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Draft ID</td><td><?php echo $doc['dID']; ?></td></tr>
                        <tr><td>Document Type</td><td><?php echo (int)$doc['dtype']; ?></td></tr>
                        <tr><td>Created</td><td><?php echo date('M d, Y H:i:s', strtotime($doc['datetime_added'])); ?> UTC</td></tr>
                        <tr><td>Last Updated</td><td><?php echo date('M d, Y H:i:s', strtotime($doc['last_update_time'])); ?> UTC</td></tr>
                        <?php if (!empty($doc['submission_time'])): ?>
                        <tr><td>Submission Time</td><td><?php echo date('M d, Y H:i:s', strtotime($doc['submission_time'])); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Has File</td><td><?php echo ['None', 'Main PDF', 'Main + Suppl PDF', 'Main + Suppl ZIP'][$hasFileVal] ?? 'Unknown'; ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- PDF Preview Panel -->
            <?php if ($hasMainFile): ?>
            <div id="panel-pdf" class="doc-tab-panel">
                <object data="/stream?type=draft&id=<?php echo htmlspecialchars((string)$doc['dID']); ?>"
                        type="application/pdf"
                        width="100%"
                        height="800px"
                        class="pdf-frame">
                    <p>
                        Your browser does not support inline PDF viewing.
                        <a href="/stream?type=draft&id=<?php echo htmlspecialchars((string)$doc['dID']); ?>">Download the PDF</a> to view it.
                    </p>
                </object>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>

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
