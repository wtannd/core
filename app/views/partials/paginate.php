<?php
/**
 * Reusable Pagination Partial
 * 
 * Expected variables:
 *   $currentPage   — Current page number
 *   $totalPages    — Total number of pages
 *   $buildPageUrl  — Closure(int $page): string
 */

if ($totalPages <= 1) return;

$windowSize = 2; // pages on each side of current
$startPage = max(1, $currentPage - $windowSize);
$endPage = min($totalPages, $currentPage + $windowSize);
?>
<nav class="paginate">
    <?php if ($currentPage > 1): ?>
        <a href="<?php echo htmlspecialchars($buildPageUrl($currentPage - 1)); ?>" class="paginate-link">&laquo; Prev</a>
    <?php else: ?>
        <span class="paginate-link disabled">&laquo; Prev</span>
    <?php endif; ?>

    <?php if ($startPage > 1): ?>
        <a href="<?php echo htmlspecialchars($buildPageUrl(1)); ?>" class="paginate-link">1</a>
        <?php if ($startPage > 2): ?>
            <span class="paginate-ellipsis">&hellip;</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i === $currentPage): ?>
            <span class="paginate-link current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($buildPageUrl($i)); ?>" class="paginate-link"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
            <span class="paginate-ellipsis">&hellip;</span>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($buildPageUrl($totalPages)); ?>" class="paginate-link"><?php echo $totalPages; ?></a>
    <?php endif; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo htmlspecialchars($buildPageUrl($currentPage + 1)); ?>" class="paginate-link">Next &raquo;</a>
    <?php else: ?>
        <span class="paginate-link disabled">Next &raquo;</span>
    <?php endif; ?>
</nav>
