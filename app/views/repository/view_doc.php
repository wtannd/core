<?php
/**
 * Document Viewer View (Published)
 * 
 * Expected $docData keys:
 *   'doc'  — Document entity
 *   'extLinks'  — from ExternalDocs
 *   'branches'  — from DocBranches+ResearchBranches
 *   'topic'     — from DocTopics+ResearchTopics or false
 *   'isSubmitter' — bool
 *   'isOnHold'    — bool
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - <?php echo htmlspecialchars($doc->title); ?></title>
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
                <?php if (!empty($branches)): ?>
                <?php foreach ($branches as $b): ?>
                    <a href="/browse?branch=<?php echo $b['bID']; ?>&range=week" class="label-pill label-branch" title="<?php echo htmlspecialchars($b['bname']); ?>">
                        <?php echo htmlspecialchars($b['abbr']); ?>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($topic): ?>
                    <a href="/browse?topic=<?php echo $topic['tID']; ?>&range=week" class="label-pill label-topic" title="<?php echo htmlspecialchars($topic['tname']); ?>">
                        <?php echo htmlspecialchars($topic['abbr']); ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($docData['isOnHold'])): ?>
                <span class="doc-tab-right status-inactive btn-small">To Be Announced!</span>
                <?php endif; ?>
                <?php if (!empty($docData['isSubmitter'])): ?>
                <span class="doc-tab-right">
                    <a href="/revise_doc?id=<?php echo $doc->dID; ?>" class="btn btn-draft btn-small">Revise Document</a>
                </span>
                <?php endif; ?>
            </div>

            <!-- Title -->
            <h1 class="doc-title"><?php echo htmlspecialchars($doc->title); ?></h1>

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

                    <!-- DOI + Metrics Line -->
                    <?php if ($doc->hasDoi()): ?>
                    <div class="doc-id-line">
                        <a href="/doc/<?php echo htmlspecialchars($doc->doi); ?>">OpenArxiv:<?php echo htmlspecialchars($doc->doi); ?></a>
                        <span class="doc-version">[<?php echo $doc->getVersionString(); ?>]</span>
                        <?php
                        $metrics = [];
                        if ($doc->main_pages > 0) $metrics[] = $doc->main_pages . ' page' . ($doc->main_pages !== 1 ? 's' : '');
                        if ($doc->main_figs > 0) $metrics[] = $doc->main_figs . ' figure' . ($doc->main_figs !== 1 ? 's' : '');
                        if ($doc->main_tabs > 0) $metrics[] = $doc->main_tabs . ' table' . ($doc->main_tabs !== 1 ? 's' : '');
                        if (!empty($metrics)): ?>
                            <span class="text-muted">, <?php echo implode(', ', $metrics); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <?php if (!empty($doc->notes)): ?>
                        <div class="doc-notes"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($doc->notes)); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Right: Sidebar -->
                <div class="doc-sidebar">
                    <!-- Submitted -->
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Submitted</div>
                        <div class="doc-sidebar-value"><?php echo $doc->getFormattedSubmitTime(); ?></div>
                    </div>

                    <!-- Announced -->
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Announced</div>
                        <div class="doc-sidebar-value">
                            <?php echo $doc->getFormattedAnnounceTime(); ?>
                        </div>
                    </div>

                    <hr class="doc-sidebar-divider">

                    <!-- Full PDF -->
                    <?php if ($doc->hasMainFile()): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Full Text PDF</div>
                        <a href="<?php echo $doc->getMainFileLink(); ?>" class="doc-file-link" download>
                            Download
                            <?php if ($doc->main_size > 0): ?>
                                <span class="doc-file-size">(<?php echo $doc->getFormattedMainSize(); ?>)</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Supplemental File -->
                    <?php if ($doc->hasSupplFile()): ?>
                    <div class="doc-sidebar-section">
                        <div class="field-label field-label-sm">Supplemental File</div>
                        <a href="<?php echo $doc->getSupplFileLink(); ?>" class="doc-file-link" download>
                            Download
                            <?php if ($doc->suppl_size > 0): ?>
                                <span class="doc-file-size">(<?php echo $doc->getFormattedSupplSize(); ?>)</span>
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
                <?php if ($doc->hasMainFile()): ?>
                <div class="doc-tab-right">
                    <button class="doc-tab" data-panel="panel-pdf">Preview PDF</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Abstract Panel -->
            <div id="panel-abstract" class="doc-tab-panel active">
                <div class="doc-abstract">
                    <?php echo nl2br(htmlspecialchars($doc->abstract)); ?>
                </div>
            </div>

            <!-- Revisions Panel -->
            <div id="panel-revisions" class="doc-tab-panel">
                <div class="revision-submitter">
                    Submitted by <a href="<?php echo $doc->getSubmitterProfileUrl(); ?>"><?php echo htmlspecialchars($doc->submitter_name ?? 'Unknown'); ?></a>
                    <?php if (!empty($doc->last_update_time)): ?>
                    | Last updated: <?php echo $doc->getFormattedLastUpdateTime(); ?>
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
                        $history = array_reverse($doc->getRevisionHistory());
                        $revCount = count($history);
                        
                        // Current version row
                        $mainFileStr = "";
                        if ($doc->version > 0) {
                            $mainFileStr = '<a href="'.$doc->getVersionedMainFileLink($doc->version).'">PDF</a>['.$doc->getFormattedMainSize().']';
                        }
                        $supplFileStr = "";
                        if ($doc->ver_suppl !== null) {
                            $extLabel = $doc->getSupplExtLabel();
                            $supplFileStr = ' + <a href="'.$doc->getVersionedSupplFileLink($doc->ver_suppl).'">'.$extLabel.'</a>['.$doc->getFormattedSupplSize().']';
                        }
                        
                        $currentNotes = ($revCount > 0) ? $history[0][4] : '';
                        ?>
                        <tr>
                            <td><?php echo $doc->getVersionString(); ?></td>
                            <td><?php echo $doc->getFormattedLastRevisionTime(); ?></td>
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

                            $rNotes = isset($history[$hIndex + 1]) ? $history[$hIndex + 1][4] : '';

                            $rVerStr = "v{$rVer}";
                            if ($rVerSuppl !== null) $rVerStr .= "+v{$rVerSuppl}";

                            $rMainFileStr = "";
                            if ($rVer > 0) {
                                $rMainFileStr = '<a href="'.$doc->getVersionedMainFileLink($rVer).'">PDF</a>['.Document::formatSize($rMainSize).']';
                            }
                            $rSupplFileStr = "";
                            if ($rVerSuppl !== null) {
                                $rExtLabel = ($rSupplExt === 2 ? 'ZIP' : 'PDF');
                                $rSupplFileStr = ' + <a href="'.$doc->getVersionedSupplFileLink($rVerSuppl).'">'.$rExtLabel.'</a>['.Document::formatSize($rSupplSize).']';
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
