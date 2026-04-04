<?php
/**
 * Shared Draft Feed Partial
 * 
 * Renders a list of document drafts.
 * Expects $documents array from controller.
 */
?>
<div class="doc-list">
    <?php if (empty($documents)): ?>
        <p>No drafts found.</p>
    <?php else: ?>
        <?php foreach ($documents as $n => $doc): ?>
            <?php
                $seq = $n + 1;
                $dID = (int)$doc['dID'];
                $hasFile = (int)($doc['has_file'] ?? 0);
                $mainSize = (int)($doc['main_size'] ?? 0);

                // Build the info bracket: [pdf, xx KB]
                $infoParts = [];
                if ($hasFile >= 1) {
                    $infoParts[] = '<a href="/stream?type=draft&id=' . $dID . '" class="feed-pdf-link">pdf</a>';
                }
                if ($mainSize > 0) {
                    $infoParts[] = number_format(round($mainSize / 1024)) . ' KB';
                }
                $infoBracket = !empty($infoParts) ? '[' . implode(', ', $infoParts) . ']' : '';

                // Date
                $dateStr = !empty($doc['submission_time'])
                    ? date('M d, Y', strtotime($doc['submission_time']))
                    : '';

                // Authors from author_list JSON
                $authorsArray = [];
                if (!empty($doc['author_list'])) {
                    $decoded = json_decode($doc['author_list'], true);
                    if (is_array($decoded['authors'])) {
                        foreach ($decoded['authors'] as $a) {
                            if (is_array($a) && isset($a[0])) {
                                $authorsArray[] = $a[0];
                            }
                        }
                    }
                }
                $totalAuthors = count($authorsArray);

                // Abstract
                $abstractRaw = $doc['abstract'] ?? '';
                $abstractFull = htmlspecialchars($abstractRaw);
                $abstractNeedsToggle = mb_strlen($abstractRaw) > 300;
                $abstractPreview = $abstractNeedsToggle
                    ? htmlspecialchars(mb_substr($abstractRaw, 0, 300))
                    : $abstractFull;
            ?>
            <div class="doc-item">
                <div class="doc-feed-header">
                    <span class="doc-feed-id">
                        <?php echo '[' . $seq . '] '; ?>
                        <a href="/docdraft?id=<?php echo $dID; ?>">Draft:<?php echo $dID; ?></a>
                        <?php if (!empty($infoBracket)): ?>
                            <span class="doc-feed-info"><?php echo $infoBracket; ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="doc-feed-date"><?php echo $dateStr; ?></span>
                </div>

                <div class="doc-feed-title"><?php echo htmlspecialchars($doc['title']); ?></div>

                <?php if ($totalAuthors > 0): ?>
                    <div class="doc-feed-authors" id="authors-<?php echo $dID; ?>">
                        <span class="authors-short">
                            <?php
                                $displayAuthors = $authorsArray;
                                $hiddenCount = 0;
                                if ($totalAuthors > 10) {
                                    $displayAuthors = array_slice($authorsArray, 0, 10);
                                    $hiddenCount = $totalAuthors - 10;
                                }
                                echo htmlspecialchars(implode(', ', $displayAuthors));
                                if ($hiddenCount > 0) {
                                    echo ' <span class="authors-hidden" id="authors-hidden-' . $dID . '" style="display:none;">, ' . htmlspecialchars(implode(', ', array_slice($authorsArray, 10))) . '</span>';
                                }
                            ?>
                        </span>
                        <?php if ($hiddenCount > 0): ?>
                            <button type="button" class="btn-toggle-authors" data-target="authors-hidden-<?php echo $dID; ?>">[+<?php echo $hiddenCount; ?> more]</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($abstractNeedsToggle): ?>
                    <div class="doc-feed-abstract">
                        <span class="abstract-text" id="abstract-text-<?php echo $dID; ?>"><?php echo $abstractPreview; ?></span><span class="abstract-full" id="abstract-full-<?php echo $dID; ?>" style="display:none;"><?php echo $abstractFull; ?></span>
                        <button type="button" class="btn-toggle-abstract" data-target-full="abstract-full-<?php echo $dID; ?>" data-target-text="abstract-text-<?php echo $dID; ?>">[more]</button>
                    </div>
                <?php elseif (!empty($abstractFull)): ?>
                    <div class="doc-feed-abstract">
                        <span class="abstract-text"><?php echo $abstractFull; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function() {
    document.addEventListener('click', function(e) {
        var btn = e.target;

        // Toggle authors
        if (btn.classList.contains('btn-toggle-authors')) {
            var targetId = btn.getAttribute('data-target');
            var el = document.getElementById(targetId);
            if (!el) return;
            if (el.style.display === 'none') {
                el.style.display = 'inline';
                btn.textContent = '[less]';
            } else {
                el.style.display = 'none';
                var count = btn.textContent.match(/\d+/);
                btn.textContent = '[+' + (count ? count[0] : '...') + ' more]';
            }
        }

        // Toggle abstract
        if (btn.classList.contains('btn-toggle-abstract')) {
            var fullId = btn.getAttribute('data-target-full');
            var textId = btn.getAttribute('data-target-text');
            var fullEl = document.getElementById(fullId);
            var textEl = document.getElementById(textId);
            if (!fullEl || !textEl) return;
            if (fullEl.style.display === 'none') {
                fullEl.style.display = 'inline';
                textEl.style.display = 'none';
                btn.textContent = '[less]';
            } else {
                fullEl.style.display = 'none';
                textEl.style.display = 'inline';
                btn.textContent = '[more]';
            }
        }
    });
})();
</script>
