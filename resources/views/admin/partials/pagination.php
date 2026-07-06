<?php
$pagination = is_array($pagination ?? null) ? $pagination : null;

if ($pagination !== null && (int) ($pagination['total'] ?? 0) > 0):
    $page = max(1, (int) ($pagination['page'] ?? 1));
    $lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
    $hasPrevious = !empty($pagination['has_previous']);
    $hasNext = !empty($pagination['has_next']);
    $basePath = (string) ($pagination['base_path'] ?? '');
    $query = is_array($pagination['query'] ?? null) ? $pagination['query'] : [];
?>
<nav class="admin-pagination" aria-label="分页导航">
    <?php if ($hasPrevious): ?>
        <a class="admin-pagination-btn" href="<?= htmlspecialchars((string) ($pagination['previous_url'] ?? '#')) ?>"><span class="admin-pagination-text">上一页</span></a>
    <?php else: ?>
        <span class="admin-pagination-btn is-disabled" aria-disabled="true"><span class="admin-pagination-text">上一页</span></span>
    <?php endif; ?>

    <span class="admin-pagination-status"><span class="admin-pagination-text">第 <?= $page ?> / <?= $lastPage ?> 页</span></span>

    <?php if ($hasNext): ?>
        <a class="admin-pagination-btn" href="<?= htmlspecialchars((string) ($pagination['next_url'] ?? '#')) ?>"><span class="admin-pagination-text">下一页</span></a>
    <?php else: ?>
        <span class="admin-pagination-btn is-disabled" aria-disabled="true"><span class="admin-pagination-text">下一页</span></span>
    <?php endif; ?>

    <form class="admin-pagination-jump" method="get" action="<?= htmlspecialchars($basePath) ?>">
        <?php foreach ($query as $key => $value): ?>
            <?php if (is_scalar($value)): ?>
                <input type="hidden" name="<?= htmlspecialchars((string) $key) ?>" value="<?= htmlspecialchars((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label for="admin-pagination-page"><span class="admin-pagination-text">跳转到</span></label>
        <input id="admin-pagination-page" type="number" name="page" min="1" max="<?= $lastPage ?>" value="<?= $page ?>" inputmode="numeric">
        <span class="admin-pagination-unit"><span class="admin-pagination-text">页</span></span>
        <button type="submit"><span class="admin-pagination-text">跳转</span></button>
    </form>
</nav>
<?php endif; ?>
