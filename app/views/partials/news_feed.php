<?php
/**
 * News Feed Partial
 * 
 * Expects $newsFeed array from controller.
 */
?>
<div class="news-list">
    <ul class="list-group list-group-flush mb-3">
    <?php foreach ($newsFeed as $news): ?>
        <li class="list-group-item"><strong><?php echo $news['title']; ?></strong>
            <strong><?php echo $news['last_update_time']; ?></strong>
            <?php echo $news['detail']; ?>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
