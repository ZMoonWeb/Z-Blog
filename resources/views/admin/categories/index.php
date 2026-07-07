<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>分类管理 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-categories-index-page">
    <?php
    $admin = $admin ?? null;
    $categories = $categories ?? [];
    $category = $category ?? [];
    $errors = $errors ?? [];
    $flash = $flash ?? null;
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    ?>

    <div class="admin-layout">
        <?php
        $active = 'categories';
        require __DIR__ . '/../partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">
            <?php if (!empty($flash['message'])): ?>
                <div class="admin-flash admin-flash-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="admin-section-actions admin-categories-head">
                <div class="admin-categories-head-copy">
                    <h2 class="admin-section-title">全部分类</h2>
                    <p class="admin-section-desc">新增、编辑和删除文章分类。</p>
                </div>
                <button class="admin-btn admin-categories-create-btn" type="button" data-category-create>新增分类</button>
            </div>

            <section class="admin-table-card admin-categories-list-card">
                <?php if (empty($categories)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-title">还没有分类</div>
                        <p class="admin-empty-desc">点击右上角按钮创建第一个分类。</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table admin-categories-table">
                        <thead>
                            <tr>
                                <th>分类</th>
                                <th>Slug</th>
                                <th>文章数</th>
                                <th>创建时间</th>
                                <th style="text-align: right;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $item): ?>
                                <tr>
                                    <td>
                                        <div class="admin-table-title"><?= htmlspecialchars($item['name']) ?></div>
                                        <?php if (!empty($item['description'])): ?>
                                            <div class="admin-table-muted"><?= htmlspecialchars($item['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code class="admin-code"><?= htmlspecialchars($item['slug']) ?></code></td>
                                    <td><span class="admin-badge admin-badge-muted"><?= (int) $item['post_count'] ?> 篇</span></td>
                                    <td><?= htmlspecialchars($item['created_at'] ?? '-') ?></td>
                                    <td>
                                        <div class="admin-row-actions">
                                            <a
                                                class="admin-btn admin-btn-secondary admin-btn-sm"
                                                href="/admin/categories/<?= (int) $item['id'] ?>/edit"
                                                data-category-edit
                                                data-category-id="<?= (int) $item['id'] ?>"
                                                data-category-name="<?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES) ?>"
                                                data-category-description="<?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES) ?>"
                                            >编辑</a>
                                            <form class="admin-inline-form" method="post" action="/admin/categories/<?= (int) $item['id'] ?>/delete" data-category-delete-form data-category-delete-name="<?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES) ?>">
                                                <?= \App\Core\Security\Csrf::field() ?>
                                                <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="admin-category-mobile-list" aria-label="手机端分类列表">
                        <?php foreach ($categories as $item): ?>
                            <?php
                            $categoryId = (int) ($item['id'] ?? 0);
                            $createdAt = trim((string) ($item['created_at'] ?? ''));
                            ?>
                            <article class="admin-category-mobile-card">
                                <div class="admin-category-mobile-main">
                                    <div class="admin-category-mobile-title-row">
                                        <div class="admin-category-mobile-title"><?= htmlspecialchars((string) ($item['name'] ?? '未命名分类')) ?></div>
                                        <span class="admin-badge admin-badge-muted"><?= (int) ($item['post_count'] ?? 0) ?> 篇</span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p><?= htmlspecialchars((string) $item['description']) ?></p>
                                    <?php else: ?>
                                        <p>暂无描述</p>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-category-mobile-meta">
                                    <code class="admin-code"><?= htmlspecialchars((string) ($item['slug'] ?? '')) ?></code>
                                    <span><?= htmlspecialchars($createdAt !== '' ? $createdAt : '-') ?></span>
                                </div>
                                <div class="admin-category-mobile-actions">
                                    <a
                                        class="admin-btn admin-btn-secondary admin-btn-sm"
                                        href="/admin/categories/<?= $categoryId ?>/edit"
                                        data-category-edit
                                        data-category-id="<?= $categoryId ?>"
                                        data-category-name="<?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES) ?>"
                                        data-category-description="<?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES) ?>"
                                    >编辑</a>
                                    <form class="admin-inline-form" method="post" action="/admin/categories/<?= $categoryId ?>/delete" data-category-delete-form data-category-delete-name="<?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES) ?>">
                                        <?= \App\Core\Security\Csrf::field() ?>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php require __DIR__ . '/../partials/pagination.php'; ?>
                <?php endif; ?>
            </section>

            <div class="admin-modal admin-category-delete-modal" id="category-delete-modal" data-admin-modal data-category-delete-modal aria-hidden="true">
                <div class="admin-modal-backdrop" data-admin-modal-close></div>
                <section class="admin-modal-panel admin-category-delete-panel" role="dialog" aria-modal="true" aria-labelledby="category-delete-title">
                    <div class="admin-modal-head">
                        <div>
                            <h2 class="admin-section-title" id="category-delete-title">删除分类</h2>
                            <p class="admin-section-desc" data-category-delete-desc>删除后，分类下的文章会设为未分类。</p>
                        </div>
                    </div>
                    <div class="admin-category-delete-body">
                        <p>确定要删除这个分类吗？</p>
                    </div>
                    <div class="admin-form-actions admin-category-delete-actions">
                        <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close>取消</button>
                        <button class="admin-btn admin-btn-danger" type="button" data-category-delete-confirm>确认删除</button>
                    </div>
                </section>
            </div>
            <div
                class="admin-modal <?= ($isEdit || !empty($errors)) ? 'is-open' : '' ?>"
                id="category-modal"
                data-admin-modal
                data-category-modal
                data-category-create-action="/admin/categories/create"
                data-category-edit-action-template="/admin/categories/__ID__/edit"
                aria-hidden="<?= ($isEdit || !empty($errors)) ? 'false' : 'true' ?>"
            >
                <div class="admin-modal-backdrop" data-admin-modal-close></div>
                <section class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
                    <div class="admin-modal-head">
                        <div>
                            <h2 class="admin-section-title" id="category-modal-title" data-category-modal-title><?= $isEdit ? '编辑分类' : '新增分类' ?></h2>
                            <p class="admin-section-desc" data-category-modal-desc><?= $isEdit ? '修改当前分类名称和描述。' : '创建一个新的文章分类。' ?></p>
                        </div>
                    </div>

                    <form method="post" action="<?= $isEdit ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories/create' ?>" data-category-form>
                        <?= \App\Core\Security\Csrf::field() ?>
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="name">分类名称</label>
                            <input class="admin-input" type="text" id="name" name="name" value="<?= htmlspecialchars($category['name'] ?? '') ?>" placeholder="例如：技术笔记" data-category-name-input>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="admin-field-error"><?= htmlspecialchars($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label" for="description">分类描述</label>
                            <textarea class="admin-textarea admin-textarea-sm" id="description" name="description" placeholder="简短描述该分类，可留空" data-category-description-input><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                            <?php if (!empty($errors['description'])): ?>
                                <div class="admin-field-error"><?= htmlspecialchars($errors['description']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-form-actions">
                            <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close>取消</button>
                            <button class="admin-btn" type="submit" data-category-submit><?= $isEdit ? '保存分类' : '创建分类' ?></button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
    <script src="/assets/js/admin/modules/theme.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/sidebar.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/modal.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/editor.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/upload-preview.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>






