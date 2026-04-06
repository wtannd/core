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

$version = (int)($doc['version'] ?? 0);
$ver_suppl = $doc['ver_suppl'];
$suppl_ext = (int)($doc['suppl_ext'] ?? 0);
$hasMainFile = $version > 0;
$hasSupplFile = $ver_suppl !== null;

$doi = $doc['doi'] ?? '';
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

// Revision history format: [version, ver_suppl, suppl_ext, last_revision_time, revision_notes, main_size, suppl_size]
$revisionHistory = json_decode($doc['revision_history'] ?? '[]', true) ?: [];
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
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
    <div class="main-container doc-container">
            <!-- Branch/Topic Labels -->
            <div class="doc-labels">
                <?php if (!empty($branches) || $topic): ?>
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
                <?php endif; ?>
                <?php if (!empty($docData['isOnHold'])): ?>
                <span class="doc-tab-right status-inactive btn-small">To Be Announced!</span>
                <?php endif; ?>
                <?php if (!empty($docData['isSubmitter'])): ?>
                <span class="doc-tab-right">
                    <a href="/revise_doc?id=<?php echo $doc['dID']; ?>" class="btn btn-draft btn-small">Revise Document</a>
                </span>
                <?php endif; ?>
            </div>

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
                        <span class="doc-version">[v<?php echo $version; ?><?php if ($ver_suppl !== null): ?>+v<?php echo $ver_suppl; ?><?php endif; ?>]</span>
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
                        <a href="/stream?id=<?php echo $doc['dID']; ?>" class="doc-file-link" download>
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
                        <a href="/stream?id=<?php echo $doc['dID']; ?>&suppl=1" class="doc-file-link" download>
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
                <div class="revision-submitter">
                    Submitted by <a href="/profile?id=<?php echo htmlspecialchars((string)$doc['submitter_id']); ?>"><?php echo htmlspecialchars($doc['submitter_name'] ?? 'Unknown'); ?></a>
                    <?php if (!empty($doc['last_update_time'])): ?>
                    | Last updated: <?php echo date('Y-m-d H:i:s', strtotime($doc['last_update_time'])) . ' UTC'; ?>
                    <?php endif; ?>
                </div>
                
                <table class="revision-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Datetime</th>
                            <th>Files</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history = array_reverse($revisionHistory);
                        $revCount = count($history);
                        
                        // Current version row
                        $verStr = "v{$version}";
                        if ($ver_suppl !== null) $verStr .= "+v{$ver_suppl}";
                        
                        $mainFileStr = "";
                        if ($version > 0) {
                            $mainFileStr = '<a href="/stream?id='.$doc['dID'].'&ver='.$version.'">PDF</a>['.$formatSize($mainSize).']';
                        }
                        $supplFileStr = "";
                        if ($ver_suppl !== null) {
                            $extLabel = ($suppl_ext === 2 ? 'ZIP' : 'PDF');
                            $supplFileStr = ' + <a href="/stream?id='.$doc['dID'].'&suppl=1&ver='.$ver_suppl.'">'.$extLabel.'</a>['.$formatSize($supplSize).']';
                        }
                        
                        // Row Current pulls notes from history[0] (which is E(v_{N-1}), describing v_N)
                        $currentNotes = ($revCount > 0) ? $history[0][4] : '';
                        ?>
                        <tr>
                            <td><?php echo $verStr; ?></td>
                            <td><?php echo !empty($doc['last_revision_time']) ? date('Y-m-d H:i:s', strtotime($doc['last_revision_time'])) . ' UTC' : date('Y-m-d H:i:s', strtotime($doc['submission_time'])) . ' UTC'; ?></td>
                            <td><?php echo $mainFileStr . $supplFileStr; ?></td>
                            <td><?php echo htmlspecialchars($currentNotes); ?></td>
                        </tr>
                        <?php
                        // History rows
                        foreach ($history as $hIndex => $rev):
                            $rVer = (int)$rev[0];
                            $rVerSuppl = ($rev[1] !== null) ? (int)$rev[1] : null;
                            $rSupplExt = (int)($rev[2] ?? 0);
                            $rDate = $rev[3] ?? '';
                            $rMainSize = (int)($rev[5] ?? 0);
                            $rSupplSize = (int)($rev[6] ?? 0);

                            // Pull notes from the NEXT entry in the current (reversed) array.
                            // history[0] is E(v_{N-1}), history[1] is E(v_{N-2}).
                            // Row history[0] (Version v_{N-1}) pulls notes from history[1] (describing v_{N-2}->v_{N-1}).
                            $rNotes = isset($history[$hIndex + 1]) ? $history[$hIndex + 1][4] : '';

                            $rVerStr = "v{$rVer}";
                            if ($rVerSuppl !== null) $rVerStr .= "+v{$rVerSuppl}";

                            $rMainFileStr = "";
                            if ($rVer > 0) {
                                $rMainFileStr = '<a href="/stream?id='.$doc['dID'].'&ver='.$rVer.'">PDF</a>['.$formatSize($rMainSize).']';
                            }
                            $rSupplFileStr = "";
                            if ($rVerSuppl !== null) {
                                $rExtLabel = ($rSupplExt === 2 ? 'ZIP' : 'PDF');
                                $rSupplFileStr = ' + <a href="/stream?id='.$doc['dID'].'&suppl=1&ver='.$rVerSuppl.'">'.$rExtLabel.'</a>['.$formatSize($rSupplSize).']';
                            }
                        ?>
                            <tr>
                                <td><?php echo $rVerStr; ?></td>
                                <td><?php echo $rDate ? date('Y-m-d H:i:s', strtotime($rDate)) . ' UTC' : '&mdash;'; ?></td>
                                <td><?php echo $rMainFileStr . $rSupplFileStr; ?></td>
                                <td><?php echo htmlspecialchars($rNotes); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PDF Preview Panel -->
            <?php if ($hasMainFile): ?>
            <div id="panel-pdf" class="doc-tab-panel">
                <object data="/stream?id=<?php echo htmlspecialchars((string)$doc['dID']); ?>"
                        type="application/pdf"
                        width="100%"
                        height="800px"
                        class="pdf-frame">
                    <p>
                        Your browser does not support inline PDF viewing.
                        <a href="/stream?id=<?php echo htmlspecialchars((string)$doc['dID']); ?>">Download the PDF</a> to view it.
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
