<?php
$post = $post ?? [];
$categories = $categories ?? [];
$errors = $errors ?? [];
$formAction = $formAction ?? '/admin/posts/create';
$isEdit = (bool) ($isEdit ?? false);
$contentMode = (string) ($post['content_mode'] ?? 'markdown');
if (!in_array($contentMode, ['text', 'markdown', 'html'], true)) {
    $contentMode = 'markdown';
}
$tagValues = preg_split('/[,，\s]+/u', (string) ($post['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$tagValues = array_values(array_unique(array_map('trim', $tagValues)));
$tagValues = array_slice($tagValues, 0, 3);
?>

<form class="admin-form-card admin-editor-form admin-writing-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
    <header class="admin-writing-topbar" aria-label="写作工具栏">
        <div class="admin-editor-toolbar admin-writing-toolbar" aria-label="编辑器工具栏">
            <button type="button" data-insert="heading1">H1</button>
            <button type="button" data-insert="heading2">H2</button>
            <button type="button" data-insert="heading3">H3</button>
            <button type="button" data-insert="heading4">H4</button>
            <button type="button" data-insert="heading5">H5</button>
            <button type="button" data-insert="heading6">H6</button>
            <button type="button" data-insert="bold">加粗</button>
            <button type="button" data-insert="italic">斜体</button>
            <button type="button" data-insert="quote">引用</button>
            <button type="button" data-insert="ul">列表</button>
            <button type="button" data-insert="ol">编号</button>
            <button type="button" data-insert="link">链接</button>
            <button type="button" data-insert="image">图片</button>
            <button type="button" data-insert="code">代码</button>
            <button type="button" data-insert="br">换行</button>
            <button type="button" data-insert="fontsize">字号</button>
        </div>

        <div class="admin-mode-tabs admin-writing-mode-tabs" role="radiogroup" aria-label="文章编辑模式">
            <label class="admin-mode-tab <?= $contentMode === 'text' ? 'active' : '' ?>">
                <input type="radio" name="content_mode" value="text" <?= $contentMode === 'text' ? 'checked' : '' ?>>
                <span>文本</span>
                <small>纯文本</small>
            </label>
            <label class="admin-mode-tab <?= $contentMode === 'markdown' ? 'active' : '' ?>">
                <input type="radio" name="content_mode" value="markdown" <?= $contentMode === 'markdown' ? 'checked' : '' ?>>
                <span>Markdown</span>
                <small>推荐</small>
            </label>
            <label class="admin-mode-tab <?= $contentMode === 'html' ? 'active' : '' ?>">
                <input type="radio" name="content_mode" value="html" <?= $contentMode === 'html' ? 'checked' : '' ?>>
                <span>HTML</span>
                <small>标签</small>
            </label>
        </div>
        <?php if (!empty($errors['content_mode'])): ?>
            <div class="admin-field-error"><?= htmlspecialchars($errors['content_mode']) ?></div>
        <?php endif; ?>
    </header>

    <div class="admin-writing-shell">
        <aside class="admin-writing-outline" aria-label="文章框架">
            <div class="admin-writing-sticky">
                <div class="admin-writing-panel-head">
                    <div class="admin-writing-panel-title">目录</div>
                </div>
                <div class="admin-editor-outline" data-editor-outline>
                    <button class="admin-outline-item admin-outline-empty" type="button">暂无小标题</button>
                </div>
                <p class="admin-writing-empty-tip">为正文增加标题，这里会生成目录</p>
            </div>
        </aside>

        <section class="admin-writing-canvas">
            <div class="admin-writing-paper">
                <div class="admin-writing-title-wrap">
                    <label class="admin-writing-label" for="title">标题</label>
                    <input class="admin-input admin-title-input admin-writing-title" type="text" id="title" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" placeholder="请输入文章标题">
                </div>
                <?php if (!empty($errors['title'])): ?>
                    <div class="admin-field-error"><?= htmlspecialchars($errors['title']) ?></div>
                <?php endif; ?>

                <div class="admin-writing-divider"></div>
                <label class="admin-writing-label admin-writing-content-label" for="content">正文</label>
                <textarea class="admin-textarea admin-editor-textarea admin-writing-content" id="content" name="content" placeholder="# 创作灵感&#10;&#10;记录工作实践、项目复盘、技术笔记或生活感悟。"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
                <?php if (!empty($errors['content'])): ?>
                    <div class="admin-field-error"><?= htmlspecialchars($errors['content']) ?></div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="admin-writing-side" aria-label="发布设置">
            <div class="admin-writing-sticky">
                <section class="admin-writing-side-section">
                    <div class="admin-writing-panel-head">
                        <div class="admin-writing-panel-title">发布设置</div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="summary">
                            摘要
                            <span class="admin-form-hint">首页展示</span>
                        </label>
                        <textarea class="admin-textarea admin-textarea-sm" id="summary" name="summary" placeholder="输入文章摘要，可留空"><?= htmlspecialchars($post['summary'] ?? '') ?></textarea>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="category_id">分类</label>
                        <select class="admin-select" id="category_id" name="category_id">
                            <option value="">未分类</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (string) ($post['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['category_id'])): ?>
                            <div class="admin-field-error"><?= htmlspecialchars($errors['category_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="status">状态</label>
                        <select class="admin-select" id="status" name="status">
                            <option value="1" <?= (int) ($post['status'] ?? 1) === 1 ? 'selected' : '' ?>>发布</option>
                            <option value="0" <?= (int) ($post['status'] ?? 1) === 0 ? 'selected' : '' ?>>草稿</option>
                        </select>
                        <?php if (!empty($errors['status'])): ?>
                            <div class="admin-field-error"><?= htmlspecialchars($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="cover_image">
                            封面图
                            <span class="admin-form-hint">留空默认 ZMoon</span>
                        </label>
                        <input class="admin-input" type="text" id="cover_image" name="cover_image" value="<?= htmlspecialchars($post['cover_image'] ?? '') ?>" placeholder="/assets/img/ZMoon.png">
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="tags">
                            标签
                            <span class="admin-form-hint">最多 3 个</span>
                        </label>
                        <input type="hidden" id="tags" name="tags" value="<?= htmlspecialchars(implode(',', $tagValues)) ?>" data-tags-hidden>
                        <div class="admin-tag-editor" data-tag-editor data-max-tags="3">
                            <div class="admin-tag-input-row">
                                <input class="admin-input" type="text" data-tag-input placeholder="输入标签">
                                <button class="admin-tag-add" type="button" data-tag-add aria-label="添加标签">+</button>
                            </div>
                            <div class="admin-tag-list" data-tag-list aria-label="已添加标签">
                                <?php foreach ($tagValues as $tag): ?>
                                    <span class="admin-tag-chip" data-tag-chip="<?= htmlspecialchars($tag) ?>">
                                        <span><?= htmlspecialchars($tag) ?></span>
                                        <button type="button" data-tag-remove aria-label="删除 <?= htmlspecialchars($tag) ?>">×</button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (!empty($errors['tags'])): ?>
                            <div class="admin-field-error"><?= htmlspecialchars($errors['tags']) ?></div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($isEdit): ?>
                    <section class="admin-writing-side-section">
                        <div class="admin-writing-panel-head">
                            <div class="admin-writing-panel-title">文章信息</div>
                        </div>
                        <p class="admin-section-desc">创建时间：<?= htmlspecialchars($post['created_at'] ?? '-') ?></p>
                        <p class="admin-section-desc">更新时间：<?= htmlspecialchars($post['updated_at'] ?? '-') ?></p>
                        <p class="admin-section-desc">固定链接：<?= htmlspecialchars($post['slug'] ?? '-') ?></p>
                    </section>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <footer class="admin-writing-bottom-bar">
        <div class="admin-writing-count" data-writing-count>共 0 字</div>
        <div class="admin-form-actions admin-editor-actions admin-writing-actions">
            <a class="admin-btn admin-btn-secondary" href="/admin/posts">取消</a>
            <button class="admin-btn admin-btn-secondary" type="submit" name="status" value="0"><?= $isEdit ? '存为草稿' : '保存草稿' ?></button>
            <button class="admin-btn" type="submit" name="status" value="1"><?= $isEdit ? '保存并发布' : '发布文章' ?></button>
        </div>
    </footer>
</form>
