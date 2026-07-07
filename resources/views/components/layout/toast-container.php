<?php

declare(strict_types=1);

$class = (string) ($class ?? 'toast-container');
$target = (string) ($target ?? 'data-toast-container');
if (preg_match('/^[A-Za-z0-9_-]+$/', $target) !== 1) {
    $target = 'data-toast-container';
}
?>
<div class="<?= htmlspecialchars($class) ?>" <?= $target ?> aria-live="polite" aria-atomic="true"></div>
