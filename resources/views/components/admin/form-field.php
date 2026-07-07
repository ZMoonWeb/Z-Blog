<?php

declare(strict_types=1);

$name = (string) ($name ?? '');
$id = (string) ($id ?? $name);
$label = (string) ($label ?? '');
$type = (string) ($type ?? 'text');
$value = (string) ($value ?? '');
$class = (string) ($class ?? 'admin-input');
?>
<label<?= $id !== '' ? ' for="' . htmlspecialchars($id) . '"' : '' ?>>
    <?php if ($label !== ''): ?>
        <span><?= htmlspecialchars($label) ?></span>
    <?php endif; ?>
    <input
        class="<?= htmlspecialchars($class) ?>"
        type="<?= htmlspecialchars($type) ?>"
        id="<?= htmlspecialchars($id) ?>"
        name="<?= htmlspecialchars($name) ?>"
        value="<?= htmlspecialchars($value) ?>"
    >
</label>
