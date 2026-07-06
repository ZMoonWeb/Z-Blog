<?php
$admin = $admin ?? null;
$settings = $settings ?? [];
$announcement = $announcement ?? ['content' => '', 'content_mode' => 'text'];
$heroSlides = $heroSlides ?? [];
$flash = $flash ?? null;

$value = static function (string $key, string $default = '') use ($settings): string {
    $settingValue = trim((string) ($settings[$key] ?? ''));
    return $settingValue !== '' ? $settingValue : $default;
};

$previewImage = static function (string $value): string {
    return trim($value) !== '' ? $value : '/assets/img/ZMoon.png';
};

$announcementMode = (string) ($announcement['content_mode'] ?? 'text');
if (!in_array($announcementMode, ['text', 'markdown', 'html'], true)) {
    $announcementMode = 'text';
}

$aboutMode = $value('about_mode', 'markdown');
if (!in_array($aboutMode, ['text', 'markdown', 'html'], true)) {
    $aboutMode = 'markdown';
}

if (count($heroSlides) === 0) {
    $heroSlides[] = ['image_url' => '', 'link_url' => '/', 'title' => ''];
}

$aboutLinkDefinitions = [
    ['label' => 'GitHub', 'icon' => 'fa-brands fa-github', 'placeholder' => '', 'aliases' => ['GitHub']],
    ['label' => 'Gitee', 'icon' => 'fa-solid fa-code-branch', 'placeholder' => '', 'aliases' => ['Gitee']],
    ['label' => 'QQ', 'icon' => 'fa-brands fa-qq', 'placeholder' => '', 'aliases' => ['QQ', 'QQ群']],
    ['label' => '邮箱', 'icon' => 'fa-solid fa-envelope', 'placeholder' => '', 'aliases' => ['邮箱', '邮件']],
    ['label' => '微信', 'icon' => 'fa-brands fa-weixin', 'placeholder' => '', 'aliases' => ['微信']],
];

$aboutLinkValueByLabel = [];
foreach (preg_split('/\R/u', (string) ($settings['about_links'] ?? '')) ?: [] as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $parts = array_map('trim', explode('|', $line, 3));
    if (count($parts) >= 3) {
        [$linkLabel, , $linkUrl] = $parts;
    } elseif (count($parts) === 2) {
        [$linkLabel, $linkUrl] = $parts;
    } else {
        continue;
    }

    if ($linkLabel !== '' && $linkUrl !== '') {
        $aboutLinkValueByLabel[$linkLabel] = $linkUrl;
    }
}

$aboutLinkRows = [];
foreach ($aboutLinkDefinitions as $definition) {
    $linkValue = $aboutLinkValueByLabel[$definition['label']] ?? '';
    foreach ($definition['aliases'] as $alias) {
        if ($linkValue === '' && isset($aboutLinkValueByLabel[$alias])) {
            $linkValue = $aboutLinkValueByLabel[$alias];
        }
    }

    $aboutLinkRows[] = [
        'label' => $definition['label'],
        'icon' => $definition['icon'],
        'placeholder' => $definition['placeholder'],
        'url' => $linkValue,
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>前台设置 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page">
    <div class="admin-layout">
        <?php
        $active = 'settings';
        require __DIR__ . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-settings-main">
            <div class="admin-toast-container" data-admin-toast-container aria-live="polite" aria-atomic="true">
                <?php if (is_array($flash)): ?>
                    <div
                        class="admin-toast-seed"
                        data-admin-toast
                        data-admin-toast-type="<?= htmlspecialchars(((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success') ?>"
                        data-admin-toast-message="<?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>"
                        hidden
                    ></div>
                <?php endif; ?>
            </div>

            <div class="admin-section-actions admin-settings-actions">
                <div>
                    <h2 class="admin-section-title">前台设置</h2>
                    <p class="admin-section-desc">按内容分区整理前台展示配置，图片统一由管理员上传并自动保存路径。</p>
                </div>
            </div>

            <div class="admin-settings-form">
                <div class="admin-settings-layout">
                    <form class="admin-settings-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="basic">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">基础信息</h3>
                            <p class="admin-section-desc">顶栏、侧栏和页脚里最常用的站点展示信息。</p>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="site_title">站点标题</label>
                                <input class="admin-input" id="site_title" name="site_title" value="<?= htmlspecialchars($value('site_title', 'Z-Blog')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="profile_name">侧栏名称</label>
                                <input class="admin-input" id="profile_name" name="profile_name" value="<?= htmlspecialchars($value('profile_name', 'Z-Blog')) ?>">
                            </div>
                        </div>

                        <div class="admin-upload-grid">
                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">顶栏图标</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('site_logo', '/assets/img/ZMoon.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview" src="<?= htmlspecialchars($previewImage($value('site_logo', '/assets/img/ZMoon.png'))) ?>" alt="顶栏图标预览">
                                <input class="admin-input" type="file" name="site_logo_file" accept="image/*">
                            </label>

                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">顶栏头像</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('site_avatar', '/assets/img/ZMoon.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview" src="<?= htmlspecialchars($previewImage($value('site_avatar', '/assets/img/ZMoon.png'))) ?>" alt="顶栏头像预览">
                                <input class="admin-input" type="file" name="site_avatar_file" accept="image/*">
                            </label>

                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">侧栏头像</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('profile_avatar', '/assets/img/ZMoon.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview" src="<?= htmlspecialchars($previewImage($value('profile_avatar', '/assets/img/ZMoon.png'))) ?>" alt="侧栏头像预览">
                                <input class="admin-input" type="file" name="profile_avatar_file" accept="image/*">
                            </label>

                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">侧栏背景图</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview admin-upload-preview-cover" src="<?= htmlspecialchars($previewImage($value('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png'))) ?>" alt="侧栏背景图预览">
                                <input class="admin-input" type="file" name="profile_cover_file" accept="image/*">
                            </label>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存基础信息</button>
                        </div>
                    </form>

                    <form class="admin-settings-section admin-settings-home-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="home">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">首页内容</h3>
                            <p class="admin-section-desc">首页轮播图单独整理，图片直接上传。</p>
                        </div>

                        <div class="admin-settings-card">
                            <div class="admin-settings-section-head admin-settings-section-head-compact">
                                <h4 class="admin-section-title">首页轮播图</h4>
                                <p class="admin-section-desc">标题和跳转链接可改，图片统一上传。</p>
                            </div>

                            <div class="admin-slide-toolbar">
                                <button class="admin-btn admin-btn-secondary admin-slide-add" type="button" data-add-hero-slide>添加轮播图</button>
                            </div>

                            <div class="admin-slide-list" data-hero-slide-list>
                                <?php foreach ($heroSlides as $index => $slide): ?>
                                    <div class="admin-slide-item" data-hero-slide-item>
                                        <div class="admin-slide-preview">
                                            <img src="<?= htmlspecialchars($previewImage((string) ($slide['image_url'] ?? ''))) ?>" alt="轮播图 <?= (int) $index + 1 ?> 预览">
                                            <button class="admin-slide-remove" type="button" data-remove-hero-slide>删除</button>
                                        </div>

                                        <div class="admin-slide-fields">
                                            <input type="hidden" name="hero_slide_existing[]" value="<?= htmlspecialchars((string) ($slide['image_url'] ?? '')) ?>">

                                            <div class="admin-form-group">
                                                <label class="admin-form-label" for="hero_slide_title_<?= (int) $index ?>">标题</label>
                                                <input class="admin-input" id="hero_slide_title_<?= (int) $index ?>" name="hero_slide_title[]" value="<?= htmlspecialchars((string) ($slide['title'] ?? '')) ?>">
                                            </div>

                                            <div class="admin-form-group">
                                                <label class="admin-form-label" for="hero_slide_link_<?= (int) $index ?>">跳转链接</label>
                                                <input class="admin-input" id="hero_slide_link_<?= (int) $index ?>" name="hero_slide_link[]" value="<?= htmlspecialchars((string) ($slide['link_url'] ?? '/')) ?>">
                                            </div>

                                            <div class="admin-form-group">
                                                <label class="admin-form-label" for="hero_slide_image_<?= (int) $index ?>">上传图片</label>
                                                <input class="admin-input" type="file" id="hero_slide_image_<?= (int) $index ?>" name="hero_slide_image[]" accept="image/*">
                                                <div class="admin-form-hint"><?= htmlspecialchars((string) ($slide['image_url'] ?? '未上传')) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <template data-hero-slide-template>
                                <div class="admin-slide-item" data-hero-slide-item>
                                    <div class="admin-slide-preview">
                                        <img src="/assets/img/ZMoon.png" alt="轮播图预览">
                                        <button class="admin-slide-remove" type="button" data-remove-hero-slide>删除</button>
                                    </div>

                                    <div class="admin-slide-fields">
                                        <input type="hidden" name="hero_slide_existing[]" value="">

                                        <div class="admin-form-group">
                                            <label class="admin-form-label" for="hero_slide_title_0">标题</label>
                                            <input class="admin-input" id="hero_slide_title_0" name="hero_slide_title[]" value="">
                                        </div>

                                        <div class="admin-form-group">
                                            <label class="admin-form-label" for="hero_slide_link_0">跳转链接</label>
                                            <input class="admin-input" id="hero_slide_link_0" name="hero_slide_link[]" value="/">
                                        </div>

                                        <div class="admin-form-group">
                                            <label class="admin-form-label" for="hero_slide_image_0">上传图片</label>
                                            <input class="admin-input" type="file" id="hero_slide_image_0" name="hero_slide_image[]" accept="image/*">
                                            <div class="admin-form-hint"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存首页内容</button>
                        </div>
                    </form>

                    <form class="admin-settings-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="announcement">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">侧栏公告</h3>
                            <p class="admin-section-desc">这里保留侧栏当前生效的单条公告内容和展示格式。</p>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="announcement_mode">侧栏公告格式</label>
                                <select class="admin-select" id="announcement_mode" name="announcement_mode">
                                    <option value="text" <?= $announcementMode === 'text' ? 'selected' : '' ?>>文本</option>
                                    <option value="markdown" <?= $announcementMode === 'markdown' ? 'selected' : '' ?>>Markdown</option>
                                    <option value="html" <?= $announcementMode === 'html' ? 'selected' : '' ?>>HTML</option>
                                </select>
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="announcement_content">侧栏公告内容</label>
                                <textarea class="admin-textarea admin-editor-textarea admin-editor-textarea-compact" id="announcement_content" name="announcement_content"><?= htmlspecialchars((string) ($announcement['content'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存侧栏公告</button>
                        </div>
                    </form>

                    <form class="admin-settings-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="about">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">关于页</h3>
                            <p class="admin-section-desc">关于页文案、图片和链接配置集中放在这里。</p>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="about_title">关于页标题</label>
                                <input class="admin-input" id="about_title" name="about_title" value="<?= htmlspecialchars($value('about_title', '关于本站')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="about_subtitle">关于页副标题</label>
                                <input class="admin-input" id="about_subtitle" name="about_subtitle" value="<?= htmlspecialchars($value('about_subtitle', '关于这里的故事，关于写作的小角落')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="about_mode">正文格式</label>
                                <select class="admin-select" id="about_mode" name="about_mode">
                                    <option value="text" <?= $aboutMode === 'text' ? 'selected' : '' ?>>文本</option>
                                    <option value="markdown" <?= $aboutMode === 'markdown' ? 'selected' : '' ?>>Markdown</option>
                                    <option value="html" <?= $aboutMode === 'html' ? 'selected' : '' ?>>HTML</option>
                                </select>
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="about_content">关于页正文</label>
                                <textarea class="admin-textarea admin-editor-textarea admin-editor-textarea-compact" id="about_content" name="about_content"><?= htmlspecialchars($value('about_content', '')) ?></textarea>
                            </div>
                        </div>

                        <div class="admin-upload-grid">
                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">关于页头像</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('about_avatar', '/assets/img/ZMoon.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview" src="<?= htmlspecialchars($previewImage($value('about_avatar', '/assets/img/ZMoon.png'))) ?>" alt="关于页头像预览">
                                <input class="admin-input" type="file" name="about_avatar_file" accept="image/*">
                            </label>

                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">关于页横幅</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('about_cover', '/assets/img/backgrounds/sidebar-profile-cover.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview admin-upload-preview-cover" src="<?= htmlspecialchars($previewImage($value('about_cover', '/assets/img/backgrounds/sidebar-profile-cover.png'))) ?>" alt="关于页横幅预览">
                                <input class="admin-input" type="file" name="about_cover_file" accept="image/*">
                            </label>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="about_skills">技能 / 关键词</label>
                                <textarea class="admin-textarea admin-textarea-sm" id="about_skills" name="about_skills"><?= htmlspecialchars($value('about_skills', "PHP\nJavaScript\nMySQL")) ?></textarea>
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="about_links">关于页链接</label>
                                <div class="admin-copy-button-list admin-about-link-list" id="about_links">
                                    <?php foreach ($aboutLinkRows as $linkIndex => $linkRow): ?>
                                        <div class="admin-copy-button-row admin-about-link-row">
                                            <div class="admin-copy-button-fixed">
                                                <span>
                                                    <i class="<?= htmlspecialchars((string) ($linkRow['icon'] ?? 'fa-solid fa-link')) ?>" aria-hidden="true"></i>
                                                    <?= htmlspecialchars((string) ($linkRow['label'] ?? '')) ?>
                                                </span>
                                                <input type="hidden" name="about_link_label[]" value="<?= htmlspecialchars((string) ($linkRow['label'] ?? '')) ?>">
                                            </div>
                                            <div class="admin-form-group">
                                                <label class="admin-form-label" for="about_link_url_<?= (int) $linkIndex ?>">链接内容</label>
                                                <input
                                                    class="admin-input"
                                                    id="about_link_url_<?= (int) $linkIndex ?>"
                                                    name="about_link_url[]"
                                                    value="<?= htmlspecialchars((string) ($linkRow['url'] ?? '')) ?>"
                                                    placeholder="<?= htmlspecialchars((string) ($linkRow['placeholder'] ?? 'https://example.com')) ?>"
                                                >
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存关于页</button>
                        </div>
                    </form>

                    <form class="admin-settings-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="guestbook">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">留言板</h3>
                            <p class="admin-section-desc">只保留留言板页面本身的标题、副标题和说明文字。</p>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="guestbook_title">留言板标题</label>
                                <input class="admin-input" id="guestbook_title" name="guestbook_title" value="<?= htmlspecialchars($value('guestbook_title', '留言板')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="guestbook_subtitle">留言板副标题</label>
                                <textarea class="admin-textarea admin-textarea-sm" id="guestbook_subtitle" name="guestbook_subtitle"><?= htmlspecialchars($value('guestbook_subtitle', '在这里，留下你想说的任何一句话')) ?></textarea>
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="guestbook_notice">留言提示</label>
                                <textarea class="admin-textarea admin-textarea-sm" id="guestbook_notice" name="guestbook_notice"><?= htmlspecialchars($value('guestbook_notice', '请文明留言，垃圾信息会被自动隐藏。留言默认公开显示。')) ?></textarea>
                            </div>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存留言板</button>
                        </div>
                    </form>

                    <form class="admin-settings-section" method="post" action="/admin/settings" enctype="multipart/form-data">
                        <input type="hidden" name="settings_scope" value="footer">
                        <div class="admin-settings-section-head">
                            <h3 class="admin-section-title">页脚</h3>
                            <p class="admin-section-desc">底部品牌、文案和跳转链接统一整理。</p>
                        </div>

                        <div class="admin-settings-group">
                            <div class="admin-form-group">
                                <label class="admin-form-label" for="footer_brand">页脚品牌名</label>
                                <input class="admin-input" id="footer_brand" name="footer_brand" value="<?= htmlspecialchars($value('footer_brand', 'Z-Blog')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="footer_link_text">页脚链接文字</label>
                                <input class="admin-input" id="footer_link_text" name="footer_link_text" value="<?= htmlspecialchars($value('footer_link_text', 'QQ交流群')) ?>">
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label" for="footer_link_url">页脚链接</label>
                                <input class="admin-input" id="footer_link_url" name="footer_link_url" value="<?= htmlspecialchars($value('footer_link_url', 'https://qm.qq.com/q/PE4qEHoF8W')) ?>">
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="footer_text">页脚正文</label>
                                <textarea class="admin-textarea admin-textarea-sm" id="footer_text" name="footer_text"><?= htmlspecialchars($value('footer_text', '© 2026 筑梦科技 · 记录想法，沉淀内容')) ?></textarea>
                            </div>

                            <div class="admin-form-group admin-form-group-full">
                                <label class="admin-form-label" for="footer_powered">Powered 文案</label>
                                <input class="admin-input" id="footer_powered" name="footer_powered" value="<?= htmlspecialchars($value('footer_powered', 'Powered by PHP · Theme inspired by clean card design')) ?>">
                            </div>
                        </div>

                        <div class="admin-upload-grid">
                            <label class="admin-upload-card">
                                <span class="admin-upload-meta">
                                    <span class="admin-upload-title">页脚 Logo</span>
                                    <span class="admin-upload-path"><?= htmlspecialchars($value('footer_logo', '/assets/img/ZMoon.png')) ?></span>
                                </span>
                                <img class="admin-upload-preview" src="<?= htmlspecialchars($previewImage($value('footer_logo', '/assets/img/ZMoon.png'))) ?>" alt="页脚 Logo 预览">
                                <input class="admin-input" type="file" name="footer_logo_file" accept="image/*">
                            </label>
                        </div>
                        <div class="admin-form-actions admin-settings-section-submit">
                            <button class="admin-btn" type="submit">保存页脚</button>
                        </div>
                    </form>

                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>
