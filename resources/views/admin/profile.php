<?php
$admin = $admin ?? null;
$settings = $settings ?? [];
$copyButtons = $copyButtons ?? [];
$flash = $flash ?? null;

$adminName = trim((string) ($admin['username'] ?? ''));
if ($adminName === '') {
    $adminName = '管理员';
}

$errors = [];
$old = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    $errors = $_SESSION['profile_errors'] ?? [];
    $old = $_SESSION['profile_old'] ?? [];
    unset($_SESSION['profile_errors'], $_SESSION['profile_old']);
}

$oldValue = static function (string $key, string $default = '') use ($old): string {
    return isset($old[$key]) ? (string) $old[$key] : $default;
};

$profileAvatar = trim((string) ($settings['profile_avatar'] ?? ''));
if ($profileAvatar === '') {
    $profileAvatar = '/assets/img/ZMoon.png';
}

$profileMottoDefault = trim((string) ($settings['profile_motto'] ?? ''));
if ($profileMottoDefault === '') {
    $profileMottoDefault = trim((string) ($settings['profile_text'] ?? ''));
}
if ($profileMottoDefault === '') {
    $profileMottoDefault = '把日常里的灵感，慢慢写成光。分享技术、生活与正在成长的想法。';
}
$profileMotto = $oldValue('profile_motto', $profileMottoDefault);

$profileCover = trim((string) ($settings['profile_cover'] ?? ''));
if ($profileCover === '') {
    $profileCover = '/assets/img/backgrounds/sidebar-profile-cover.png';
}

$profileHomeCover = trim((string) ($settings['profile_home_cover'] ?? ''));
if ($profileHomeCover === '') {
    $profileHomeCover = $profileCover;
}

$copyButtonDefinitions = [
    ['label' => 'GitHub', 'placeholder' => '', 'aliases' => ['GitHub']],
    ['label' => 'Gitee', 'placeholder' => '', 'aliases' => ['Gitee']],
    ['label' => 'QQ', 'placeholder' => '', 'aliases' => ['QQ', 'QQ群']],
    ['label' => '邮箱', 'placeholder' => '', 'aliases' => ['邮箱', '邮件']],
    ['label' => '微信', 'placeholder' => '', 'aliases' => ['微信']],
];

$copyButtonValueByLabel = [];
foreach ($copyButtons as $button) {
    $label = trim((string) ($button['label'] ?? ''));
    if ($label !== '') {
        $copyButtonValueByLabel[$label] = (string) ($button['copy_value'] ?? '');
    }
}

$oldCopyLabels = isset($old['copy_button_label']) && is_array($old['copy_button_label']) ? $old['copy_button_label'] : [];
$oldCopyValues = isset($old['copy_button_value']) && is_array($old['copy_button_value']) ? $old['copy_button_value'] : [];
if (!empty($oldCopyLabels) || !empty($oldCopyValues)) {
    $copyButtonValueByLabel = [];
    $oldTotal = max(count($oldCopyLabels), count($oldCopyValues));
    for ($oldIndex = 0; $oldIndex < $oldTotal; $oldIndex++) {
        $oldLabel = trim((string) ($oldCopyLabels[$oldIndex] ?? ''));
        if ($oldLabel !== '') {
            $copyButtonValueByLabel[$oldLabel] = (string) ($oldCopyValues[$oldIndex] ?? '');
        }
    }
}

$copyButtonRows = [];
foreach ($copyButtonDefinitions as $definition) {
    $copyValue = $copyButtonValueByLabel[$definition['label']] ?? '';
    foreach ($definition['aliases'] as $alias) {
        if ($copyValue === '' && isset($copyButtonValueByLabel[$alias])) {
            $copyValue = $copyButtonValueByLabel[$alias];
        }
    }

    $copyButtonRows[] = [
        'label' => $definition['label'],
        'placeholder' => $definition['placeholder'],
        'copy_value' => $copyValue,
    ];
}

$copyButtonIconByLabel = [];
foreach ($copyButtons as $button) {
    $label = trim((string) ($button['label'] ?? ''));
    if ($label !== '') {
        $copyButtonIconByLabel[$label] = trim((string) ($button['icon_svg'] ?? ''));
    }
}

$copyButtonClass = static function (string $label): string {
    return match ($label) {
        'GitHub' => 'copy-button-github',
        'Gitee' => 'copy-button-gitee',
        'QQ' => 'copy-button-qq',
        '邮箱' => 'copy-button-email',
        '微信' => 'copy-button-wechat',
        default => 'copy-button-default',
    };
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>个人资料 - Z-Blog Admin</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-profile-page">
    <div class="admin-layout">
        <?php
        $active = 'profile';
        require __DIR__ . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-profile-main">
            <div class="admin-section-actions admin-profile-actions">
                <div>
                    <h2 class="admin-section-title">个人资料</h2>
                    <p class="admin-section-desc">维护头像、座右铭、复制内容和登录信息。</p>
                </div>
            </div>

            <?php if ($flash !== null): ?>
                <div class="admin-flash admin-flash-<?= htmlspecialchars(((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success') ?>">
                    <?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="admin-flash admin-flash-error admin-profile-errors">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars((string) $error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="admin-profile-form" method="post" action="/admin/profile" enctype="multipart/form-data">
                <?= \App\Core\Security\Csrf::field() ?>
                <section class="admin-profile-clean" aria-label="个人资料表单" data-profile-panel-root>
                    <div class="profile-clean-editor">
                        <div class="profile-clean-tabs" role="tablist" aria-label="个人资料分组">
                            <button class="profile-clean-tab is-active" type="button" role="tab" id="profile-tab-identity" aria-selected="true" aria-controls="profile-panel-identity" data-profile-tab="identity">
                                身份
                            </button>
                            <button class="profile-clean-tab" type="button" role="tab" id="profile-tab-contact" aria-selected="false" aria-controls="profile-panel-contact" data-profile-tab="contact">
                                联系
                            </button>
                            <button class="profile-clean-tab" type="button" role="tab" id="profile-tab-security" aria-selected="false" aria-controls="profile-panel-security" data-profile-tab="security">
                                安全
                            </button>
                        </div>

                        <div class="admin-profile-panel-window profile-clean-window">
                            <div class="profile-clean-panel-track" data-profile-panel-track>
                                <section class="profile-clean-panel is-active" id="profile-panel-identity" role="tabpanel" aria-labelledby="profile-tab-identity" data-profile-panel="identity">
                                    <h3 class="profile-clean-panel-title">身份资料</h3>

                                    <div class="profile-clean-fields">
                                        <div class="profile-clean-field profile-clean-avatar-field">
                                            <span class="admin-form-label">个人头像</span>
                                            <label class="admin-profile-avatar-picker profile-clean-avatar-picker profile-clean-avatar-inline" aria-label="更换个人头像">
                                                <span class="profile-clean-avatar-frame">
                                                    <img class="admin-profile-avatar" src="<?= htmlspecialchars($profileAvatar) ?>" alt="<?= htmlspecialchars($adminName) ?> 头像">
                                                </span>
                                                <span class="admin-profile-avatar-action">更换头像</span>
                                                <input class="admin-profile-avatar-input" type="file" name="profile_avatar_file" accept="image/*">
                                            </label>
                                        </div>
                                        <div class="profile-clean-field">
                                            <label class="admin-form-label" for="username">用户名</label>
                                            <input class="admin-input" id="username" name="username" value="<?= htmlspecialchars($oldValue('username', $adminName)) ?>" required maxlength="50" autocomplete="username" data-profile-name-input>
                                        </div>

                                        <div class="profile-clean-field profile-clean-field-top">
                                            <label class="admin-form-label" for="profile_motto">个人座右铭</label>
                                            <textarea class="admin-textarea admin-profile-motto-input" id="profile_motto" name="profile_motto" maxlength="300" rows="4" data-profile-motto-input><?= htmlspecialchars($profileMotto) ?></textarea>
                                        </div>

                                        <div class="profile-clean-field profile-clean-field-top">
                                            <label class="admin-form-label" for="profile_home_cover_file">个人主页背景图</label>
                                            <label class="profile-clean-cover-upload" for="profile_home_cover_file">
                                                <span class="profile-clean-cover-media">
                                                    <img src="<?= htmlspecialchars($profileHomeCover) ?>" alt="个人主页背景图预览" data-profile-home-cover-preview>
                                                </span>
                                                <span class="profile-clean-cover-meta">
                                                    <strong>选择背景图</strong>
                                                    <span data-profile-home-cover-path><?= htmlspecialchars($profileHomeCover) ?></span>
                                                </span>
                                                <input class="profile-clean-file-input" id="profile_home_cover_file" name="profile_home_cover_file" type="file" accept="image/*" data-profile-home-cover-input>
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-clean-panel" id="profile-panel-contact" role="tabpanel" aria-labelledby="profile-tab-contact" data-profile-panel="contact" aria-hidden="true">
                                    <h3 class="profile-clean-panel-title">复制内容</h3>

                                    <div class="profile-clean-copy-list" id="profile_copy_buttons">
                                        <?php foreach ($copyButtonRows as $copyIndex => $copyButton): ?>
                                            <div class="profile-clean-copy-item">
                                                <label class="admin-form-label" for="profile_copy_button_value_<?= (int) $copyIndex ?>">
                                                    <?= htmlspecialchars((string) ($copyButton['label'] ?? '')) ?>
                                                </label>
                                                <input type="hidden" name="copy_button_label[]" value="<?= htmlspecialchars((string) ($copyButton['label'] ?? '')) ?>">
                                                <input class="admin-input" id="profile_copy_button_value_<?= (int) $copyIndex ?>" name="copy_button_value[]" value="<?= htmlspecialchars((string) ($copyButton['copy_value'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string) ($copyButton['placeholder'] ?? '')) ?>" data-profile-copy-input="<?= htmlspecialchars((string) ($copyButton['label'] ?? '')) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section class="profile-clean-panel" id="profile-panel-security" role="tabpanel" aria-labelledby="profile-tab-security" data-profile-panel="security" aria-hidden="true">
                                    <h3 class="profile-clean-panel-title">登录安全</h3>

                                    <div class="profile-clean-fields">
                                        <div class="profile-clean-field">
                                            <label class="admin-form-label" for="current_password">当前密码</label>
                                            <input class="admin-input" id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="修改密码时填写">
                                        </div>

                                        <div class="profile-clean-field">
                                            <label class="admin-form-label" for="new_password">新密码</label>
                                            <input class="admin-input" id="new_password" name="new_password" type="password" minlength="6" autocomplete="new-password" placeholder="至少 6 个字符">
                                        </div>

                                        <div class="profile-clean-field">
                                            <label class="admin-form-label" for="confirm_password">确认新密码</label>
                                            <input class="admin-input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" placeholder="再次输入新密码">
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div class="profile-clean-actions">
                            <a class="admin-btn admin-btn-secondary" href="/admin">取消</a>
                            <button class="admin-btn" type="submit">保存修改</button>
                        </div>
                    </div>
                </section>
            </form>
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
