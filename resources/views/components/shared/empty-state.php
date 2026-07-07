<?php

declare(strict_types=1);

$message = (string) ($message ?? '');
?>
<div class="empty-state">
    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
</div>
