<?php

declare(strict_types=1);

$id = (string) ($id ?? '');
$title = (string) ($title ?? '');
$content = (string) ($content ?? '');
?>
<div class="admin-modal"<?= $id !== '' ? ' id="' . htmlspecialchars($id) . '"' : '' ?> hidden>
    <div class="admin-modal-panel">
        <?php if ($title !== ''): ?>
            <h2><?= htmlspecialchars($title) ?></h2>
        <?php endif; ?>
        <?= $content ?>
    </div>
</div>
