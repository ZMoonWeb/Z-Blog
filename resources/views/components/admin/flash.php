<?php

declare(strict_types=1);

$flash = $flash ?? ($_SESSION['admin_flash'] ?? null);
if (!is_array($flash)) {
    return;
}

$type = ((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success';
$message = (string) ($flash['message'] ?? '');
?>
<div class="admin-toast-seed" data-admin-toast data-admin-toast-type="<?= htmlspecialchars($type) ?>" data-admin-toast-message="<?= htmlspecialchars($message) ?>" hidden></div>
