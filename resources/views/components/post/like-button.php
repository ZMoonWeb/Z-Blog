<?php

declare(strict_types=1);

$slug = (string) ($slug ?? '');
$liked = !empty($liked);
$count = (int) ($count ?? 0);
?>
<button class="post-like-button<?= $liked ? ' is-liked' : '' ?>" type="button" data-like-slug="<?= htmlspecialchars($slug) ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
    <span data-like-count><?= $count ?></span>
</button>
