<?php

declare(strict_types=1);

$label = (string) ($label ?? '');
$icon = (string) ($icon ?? '');
$type = (string) ($type ?? 'button');
$class = (string) ($class ?? 'icon-button');
?>
<button class="<?= htmlspecialchars($class) ?>" type="<?= htmlspecialchars($type) ?>"<?= $label !== '' ? ' aria-label="' . htmlspecialchars($label) . '"' : '' ?>>
    <?= $icon ?>
</button>
