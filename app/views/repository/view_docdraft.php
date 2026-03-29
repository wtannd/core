<?php
/**
 * Document Viewer View (Draft)
 */
$doc = $docData['document'];
$authorData = json_decode($doc['author_list'], true) ?? [];
$authors = $authorData['authors'] ?? [];
$affiliations = $authorData['affiliations'] ?? [];

$draftAuthors = $docData['draftAuthors'] ?? []; // From DB DocDraftAuthors
$isFullyApproved = $docData['isFullyApproved'] ?? true;
$mID = (int)($_SESSION['mID'] ?? 0);
$isSubmitter = (int)$doc['submitter_ID'] === $mID;

$hasFileVal = (int)($doc['has_file'] ?? 0);
$hasMainFile = $hasFileVal > 0;
$hasSupplFile = ($hasFileVal === 2 || $hasFileVal === 3);

// Check if user is a co-author who hasn't approved yet
$userApprovalNeeded = false;
foreach ($draftAuthors as $da) {
    if ((int)$da['mID'] === $mID && (int)$da['approved'] === 0) {
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
    <title><?php echo SITE_TITLE; ?> - [Draft] <?php echo htmlspecialchars($doc['title']); ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="document-viewer">
            <a href="/" class="btn-back">&larr; Back to Dashboard</a>
            
            <div class="draft-tag">DRAFT MODE</div>

            <div class="meta-info">
                <span>dID: <?php echo htmlspecialchars((string)$doc['dID']); ?></span> | 
                <span>Draft saved on: <?php echo date('F d, Y H:i', strtotime($doc['datetime_added'])); ?> UTC</span>
            </div>

            <h1><?php echo htmlspecialchars($doc['title'] ?: '[Untitled Draft]'); ?></h1>

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
                                        <span class="status-approved">✓ Approved</span>
                                    <?php else: ?>
                                        <span class="status-pending">⧖ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($userApprovalNeeded): ?>
                    <form action="/draft/approve" method="POST" style="margin-bottom: 1rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="dID" value="<?php echo $doc['dID']; ?>">
                        <button type="submit" class="btn btn-primary" style="background: #28a745; width: auto;">Approve this Draft</button>
                    </form>
                <?php endif; ?>

                <?php if ($isSubmitter): ?>
                    <div style="display: flex; gap: 10px;">
                        <form action="/draft/finalize" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="dID" value="<?php echo $doc['dID']; ?>">
                            <button type="submit" class="btn btn-submit <?php echo !$isFullyApproved ? 'btn-disabled' : ''; ?>" <?php echo !$isFullyApproved ? 'disabled' : ''; ?> style="width: auto;">
                                Finalize Submission
                            </button>
                            <?php if (!$isFullyApproved): ?>
                                <p><small style="color: #666;">Waiting for all co-authors to approve.</small></p>
                            <?php endif; ?>
                        </form>

                        <a href="/draft/edit?id=<?php echo $doc['dID']; ?>" class="btn" style="background: #eee; color: #333; width: auto; padding: 0.75rem 1rem;" onclick="return confirm('Editing this draft will unlock it and reset all current co-author approvals. Continue?');">
                            Edit Draft
                        </a>
                    </div>
                <?php endif; ?>
            </div>

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

            <div class="abstract-section">
                <h3>Abstract</h3>
                <p><?php echo nl2br(htmlspecialchars($doc['abstract'] ?: '[No abstract provided]')); ?></p>
            </div>

            <?php if (!empty($doc['notes'])): ?>
                <div class="meta-info" style="border-bottom: none; margin-bottom: 1rem;">
                    <strong>Notes:</strong>
                    <p><?php echo nl2br(htmlspecialchars($doc['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($doc['ext_url'])): ?>
                <div class="meta-info" style="border-bottom: none; margin-bottom: 1rem;">
                    <strong>External URL:</strong>
                    <a href="<?php echo htmlspecialchars($doc['ext_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo htmlspecialchars($doc['ext_url']); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php 
            $linkList = json_decode($doc['link_list'] ?? '[]', true);
            if (!empty($linkList)): 
            ?>
                <div class="meta-info" style="border-bottom: none; margin-bottom: 1rem;">
                    <strong>Related Links:</strong>
                    <ul>
                        <?php foreach ($linkList as $link): 
                            $sID = (int)$link[0];
                            $sourceName = $link[1];
                            $url = $link[2];
                            $href = (strpos($url, 'http') === 0) ? $url : 'https://doi.org/' . $url;
                        ?>
                            <li>
                                [<?php echo htmlspecialchars($sourceName); ?>] 
                                <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars($url); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($hasSupplFile): ?>
                <a href="/stream?type=draft_suppl&id=<?php echo $doc['dID']; ?>" class="suppl-download">
                    Download Supplemental File
                </a>
            <?php endif; ?>

            <div class="pdf-viewer">
                <h3>Document Content</h3>
                <?php if ($hasMainFile): ?>
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
                <?php elseif (!empty($doc['full_text'])): ?>
                    <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap;">
                        <?php echo nl2br(htmlspecialchars($doc['full_text'])); ?>
                    </div>
                <?php else: ?>
                    <p><em>No PDF file uploaded for this draft.</em></p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
