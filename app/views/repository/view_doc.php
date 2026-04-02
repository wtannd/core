<?php
/**
 * Document Viewer View (Published)
 * 
 * Expected $docData keys:
 *   'document'  — Documents row array
 *   'authors'   — decoded author_list JSON
 *   'extLinks'  — from ExternalDocs
 *   'branches'  — from DocBranches+ResearchBranches
 *   'topic'     — from DocTopics+ResearchTopics or false
 */
$doc = $docData['document'];
$authorData = json_decode($doc['author_list'], true) ?? [];
$authors = $authorData['authors'] ?? [];
$affiliations = $authorData['affiliations'] ?? [];
$branches = $docData['branches'] ?? [];
$topic = $docData['topic'] ?? false;
$extLinks = $docData['extLinks'] ?? [];

$hasFileVal = (int)($doc['has_file'] ?? 0);
$hasMainFile = $hasFileVal > 0;
$hasSupplFile = ($hasFileVal === 2 || $hasFileVal === 3);

$doi = $doc['doi'] ?? '';
$version = (int)($doc['version'] ?? 1);
$mainPages = (int)($doc['main_pages'] ?? 0);
$mainFigs = (int)($doc['main_figs'] ?? 0);
$mainTabs = (int)($doc['main_tabs'] ?? 0);
$mainSize = (int)($doc['main_size'] ?? 0);
$supplSize = (int)($doc['suppl_size'] ?? 0);

// Format file size
$formatSize = function (int $bytes): string {
    if ($bytes <= 0) return '';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
};

// Revision history
$revisionHistory = json_decode($doc['revision_history'] ?? '[]', true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - <?php echo htmlspecialchars($doc['title']); ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
    <div class="document-viewer">
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
            <h1 class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></h1>

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

                    <!-- DOI + Metrics Line -->
                    <?php if (!empty($doi)): ?>
                    <div class="doc-id-line">
                        <a href="/doc/<?php echo htmlspecialchars($doi); ?>">OpenArxiv:<?php echo htmlspecialchars($doi); ?></a>
                        <span class="doc-version">[v<?php echo $version; ?>]</span>
                        <?php
                        $metrics = [];
                        if ($mainPages > 0) $metrics[] = $mainPages . ' page' . ($mainPages !== 1 ? 's' : '');
                        if ($mainFigs > 0) $metrics[] = $mainFigs . ' figure' . ($mainFigs !== 1 ? 's' : '');
                        if ($mainTabs > 0) $metrics[] = $mainTabs . ' table' . ($mainTabs !== 1 ? 's' : '');
                        if (!empty($metrics)): ?>
                            <span class="text-muted">, <?php echo implode(', ', $metrics); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <?php if (!empty($doc['notes'])): ?>
                        <div class="doc-notes"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($doc['notes'])); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Right: Sidebar -->
                <div class="doc-sidebar">
                    <!-- Submitted -->
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Submitted</div>
                        <div class="doc-sidebar-value"><?php echo date('M d, Y', strtotime($doc['submission_time'])); ?></div>
                    </div>

                    <!-- Announced -->
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Announced</div>
                        <div class="doc-sidebar-value">
                            <?php echo !empty($doc['announce_time']) ? date('M d, Y', strtotime($doc['announce_time'])) : '&mdash;'; ?>
                        </div>
                    </div>

                    <hr class="doc-sidebar-divider">

                    <!-- Full PDF -->
                    <?php if ($hasMainFile): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Full Text PDF</div>
                        <a href="/stream?type=doc&id=<?php echo $doc['dID']; ?>" class="doc-file-link" download>
                            Download
                            <?php if ($mainSize > 0): ?>
                                <span class="doc-file-size">(<?php echo $formatSize($mainSize); ?>)</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Supplemental File -->
                    <?php if ($hasSupplFile): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Supplemental File</div>
                        <a href="/stream?type=doc&id=<?php echo $doc['dID']; ?>&suppl" class="doc-file-link" download>
                            Download
                            <?php if ($supplSize > 0): ?>
                                <span class="doc-file-size">(<?php echo $formatSize($supplSize); ?>)</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- External Links -->
                    <?php if (!empty($extLinks)): ?>
                    <div class="doc-sidebar-section">
                        <details>
                            <summary>External Links</summary>
                            <div>
                                <?php foreach ($extLinks as $link):
                                    $sourceName = $link[1];
                                    $url = $link[2];
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

            <?php if (!empty($docData['isSubmitter'])): ?>
            <div class="action-row">
                <a href="/revise_doc?id=<?php echo $doc['dID']; ?>" class="btn btn-draft">Revise Document</a>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="doc-tabs">
                <div class="doc-tab-left">
                    <button class="doc-tab active" data-panel="panel-abstract">Abstract</button>
                    <button class="doc-tab" data-panel="panel-revisions">Revisions</button>
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
                    <?php echo nl2br(htmlspecialchars($doc['abstract'])); ?>
                </div>
            </div>

            <!-- Revisions Panel -->
            <div id="panel-revisions" class="doc-tab-panel">
                <?php if (!empty($revisionHistory)): ?>
                    <table class="revision-table">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revisionHistory as $rev):
                                $revVer = (int)($rev[0] ?? 0);
                                $revDate = $rev[1] ?? '';
                                $revNotes = $rev[2] ?? '';
                                $revMainSize = (int)($rev[3] ?? 0);
                                $revSupplSize = (int)($rev[4] ?? 0);
                                $revSizeParts = [];
                                if ($revMainSize > 0) $revSizeParts[] = $formatSize($revMainSize);
                                if ($revSupplSize > 0) $revSizeParts[] = '+' . $formatSize($revSupplSize);
                            ?>
                                <tr>
                                    <td>v<?php echo $revVer; ?></td>
                                    <td><?php echo $revDate ? date('M d, Y', strtotime($revDate)) : '&mdash;'; ?></td>
                                    <td><?php echo htmlspecialchars($revNotes); ?></td>
                                    <td><?php echo !empty($revSizeParts) ? implode(' ', $revSizeParts) : '&mdash;'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><em>No revision history available.</em></p>
                <?php endif; ?>
            </div>

            <!-- PDF Preview Panel -->
            <?php if ($hasMainFile): ?>
            <div id="panel-pdf" class="doc-tab-panel">
                <object data="/stream?type=doc&id=<?php echo htmlspecialchars((string)$doc['dID']); ?>"
                        type="application/pdf"
                        width="100%"
                        height="800px"
                        class="pdf-frame">
                    <p>
                        Your browser does not support inline PDF viewing.
                        <a href="/stream?type=doc&id=<?php echo htmlspecialchars((string)$doc['dID']); ?>">Download the PDF</a> to view it.
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
