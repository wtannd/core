<?php
// Set defaults in case they aren't defined before including
$blockId         = htmlspecialchars($blockId ?? uniqid('block_'));
$blockTitle      = htmlspecialchars($blockTitle ?? 'Section Title');
$isBlockDisabled = $isBlockDisabled ?? false; 
?>

<div class="form-block" id="<?php echo $blockId; ?>">
    <div class="block-header">
        <h3><?php echo $blockTitle; ?></h3>
        
        <?php if ($isBlockDisabled): ?>
            <button type="button" class="btn-toggle-edit" onclick="toggleBlock('<?php echo $blockId; ?>', this)">
                Edit
            </button>
        <?php endif; ?>
    </div>

    <div class="block-body <?php echo ($isBlockDisabled) ? 'is-disabled' : ''; ?>">
        <fieldset <?php echo ($isBlockDisabled) ? 'disabled="disabled"' : ''; ?>>
