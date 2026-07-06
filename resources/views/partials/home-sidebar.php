<?php
$siteSettings = $siteSettings ?? [];
$copyButtons = $copyButtons ?? [];
$announcement = $announcement ?? null;

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$profileCover = $setting('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png');
$profileAvatar = $setting('profile_avatar', '/assets/img/ZMoon.png');
$profileName = $setting('profile_name', 'Z-Blog');
$profileText = $setting('profile_motto', $setting('profile_text', '把日常里的灵感，慢慢写成光。分享技术、生活与正在成长的想法。'));

$fallbackCopyButtonIcons = [
    'GitHub' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.58 2 12.26c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.73c-2.78.62-3.37-1.37-3.37-1.37-.45-1.19-1.11-1.5-1.11-1.5-.91-.64.07-.63.07-.63 1 .07 1.53 1.06 1.53 1.06.9 1.57 2.36 1.12 2.93.85.09-.67.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.06 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.31.1-2.71 0 0 .84-.28 2.75 1.05A9.29 9.29 0 0 1 12 7c.85 0 1.71.12 2.51.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.4.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.93-2.34 4.8-4.57 5.05.36.32.68.95.68 1.91v2.83c0 .27.18.59.69.49A10.16 10.16 0 0 0 22 12.26C22 6.58 17.52 2 12 2Z"></path></svg>',
    'Gitee' => '<svg t="1779002958566" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2521" width="200" height="200" aria-hidden="true"><path d="M512 1024C229.2224 1024 0 794.7776 0 512S229.2224 0 512 0s512 229.2224 512 512-229.2224 512-512 512z m259.1488-568.8832H480.4096a25.2928 25.2928 0 0 0-25.2928 25.2928l-0.0256 63.2064c0 13.952 11.3152 25.2928 25.2672 25.2928h177.024c13.9776 0 25.2928 11.3152 25.2928 25.2672v12.6464a75.8528 75.8528 0 0 1-75.8528 75.8528H366.592a25.2928 25.2928 0 0 1-25.2672-25.2928v-240.1792a75.8528 75.8528 0 0 1 75.8272-75.8528h353.9456a25.2928 25.2928 0 0 0 25.2672-25.2928l0.0768-63.2064a25.2928 25.2928 0 0 0-25.2672-25.2928H417.152a189.6192 189.6192 0 0 0-189.6192 189.6448v353.9456c0 13.9776 11.3152 25.2928 25.2928 25.2928h372.9408a170.6496 170.6496 0 0 0 170.6496-170.6496v-145.408a25.2928 25.2928 0 0 0-25.2928-25.2672z" fill="#C71D23" p-id="2522"></path></svg>',
    'QQ' => '<svg t="1779002974473" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3504" width="200" height="200" aria-hidden="true"><path d="M148.859845 404.057356c-5.11465 15.34395 0 20.4586 0 76.719751 0 15.34395-61.375801 76.719751-86.949052 143.210202-25.57325 66.490451-25.57325 138.095552 10.2293 163.668803 35.802551 30.6879 71.605101-92.063701 76.719752-71.605101 0 5.11465 5.11465 15.34395 5.11465 25.57325 15.34395 35.802551 35.802551 71.605101 61.375801 102.293002 5.11465 5.11465-35.802551 20.4586-61.375801 61.3758-25.57325 40.917201 10.2293 117.636952 132.980902 117.636952 158.554152 0 199.471353-56.261151 199.471353-56.261151h51.1465c10.2293 0 86.949051 66.490451 194.356703 56.261151 184.127403-20.4586 158.554152-81.834401 143.210202-122.751602-15.34395-40.917201-66.490451-61.375801-66.490451-61.375801 46.031851-51.146501 51.146501-76.719751 66.490451-122.751601 5.11465-20.4586 51.146501 102.293002 81.834402 71.605101 15.34395-10.2293 40.917201-61.375801 15.34395-163.668803s-81.834401-127.866252-81.834401-143.210202V404.057356c-10.2293-35.802551-30.6879-25.57325-30.687901-35.802551 0-204.586003-153.439502-368.254805-342.681555-368.254805S174.433095 163.668802 174.433095 368.254805c0 15.34395-15.34395 5.11465-25.57325 35.802551z m0 0" fill="#4A9AFD" p-id="3505"></path></svg>',
    '邮箱' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2v.25l8 4.8 8-4.8V8.2l-8 4.8-8-4.8Z"></path></svg>',
    '微信' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.4 4.2c-4.1 0-7.4 2.76-7.4 6.16 0 1.96 1.1 3.63 2.87 4.8l-.72 2.16 2.51-1.26c.87.25 1.76.38 2.74.38.35 0 .69-.02 1.02-.06a5.43 5.43 0 0 1-.28-1.72c0-3.04 2.9-5.5 6.47-5.5.07 0 .14 0 .21.02-.62-2.82-3.67-4.98-7.42-4.98Zm-2.4 3.2a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.8 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.82 3.05c-2.96 0-5.36 1.9-5.36 4.25s2.4 4.25 5.36 4.25c.66 0 1.28-.1 1.88-.28l2.05 1.03-.58-1.76C21.19 17.13 22 15.98 22 14.7c0-2.35-2.4-4.25-5.38-4.25Zm-1.76 2.46a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Zm3.52 0a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Z"></path></svg>',
];
?>

<aside class="home-sidebar" aria-label="侧边栏">
    <section class="profile-card" aria-label="个人资料">
        <div class="profile-cover">
            <img src="<?= htmlspecialchars($profileCover) ?>" alt="<?= htmlspecialchars($profileName) ?> 个人横幅">
        </div>

        <div class="profile-main">
            <div class="profile-avatar-wrap">
                <img class="profile-avatar" src="<?= htmlspecialchars($profileAvatar) ?>" alt="<?= htmlspecialchars($profileName) ?> 头像">
                <span class="profile-status" aria-label="在线"></span>
            </div>

            <h1><?= htmlspecialchars($profileName) ?></h1>
            <p><?= htmlspecialchars($profileText) ?></p>

            <?php if (!empty($copyButtons)): ?>
                <div class="profile-socials" aria-label="复制按钮">
                    <?php foreach ($copyButtons as $button): ?>
                        <?php
                        $label = trim((string) ($button['label'] ?? '复制'));
                        $copyValue = (string) ($button['copy_value'] ?? '');
                        $iconSvg = trim((string) ($button['icon_svg'] ?? ''));
                        $iconSvg = $iconSvg !== '' ? $iconSvg : ($fallbackCopyButtonIcons[$label] ?? '');
                        $buttonClass = match ($label) {
                            'GitHub' => 'copy-button-github',
                            'Gitee' => 'copy-button-gitee',
                            'QQ' => 'copy-button-qq',
                            '邮箱' => 'copy-button-email',
                            '微信' => 'copy-button-wechat',
                            default => 'copy-button-default',
                        };
                        ?>
                        <?php if ($copyValue !== ''): ?>
                            <button class="<?= htmlspecialchars($buttonClass) ?>" type="button" data-copy-value="<?= htmlspecialchars($copyValue) ?>" aria-label="复制<?= htmlspecialchars($label) ?>">
                                <?php if ($iconSvg !== ''): ?>
                                    <?= $iconSvg ?>
                                <?php else: ?>
                                    <span class="copy-button-text"><?= htmlspecialchars(mb_substr($label, 0, 2)) ?></span>
                                <?php endif; ?>
                                <span class="copy-tooltip" role="status" aria-live="polite"></span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($announcement !== null): ?>
        <section class="notice-card" aria-labelledby="sidebar-notice-title">
            <div class="notice-card-head">
                <span class="notice-card-icon" aria-hidden="true">
                    <svg class="notice-card-icon-light" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M79.7696 689.8688h876.4928v107.2128a111.7696 111.7696 0 0 1-111.7696 111.7696H191.5392a111.7696 111.7696 0 0 1-111.7696-111.7696v-107.2128z" fill="#FDA338"></path><path d="M853.8624 268.4416h-87.04l-163.2768-145.1008A129.28 129.28 0 0 0 432.384 122.88L267.4176 268.4416H182.1696a133.12 133.12 0 0 0-133.12 133.12v404.8384a133.12 133.12 0 0 0 133.12 133.12h671.6928a133.12 133.12 0 0 0 133.12-133.12V401.5616a133.12 133.12 0 0 0-133.12-133.12zM472.9856 168.96a67.7376 67.7376 0 0 1 89.7024 0l111.5136 99.1744H360.2944z m452.5568 637.44a71.68 71.68 0 0 1-71.68 71.68H182.1696a71.68 71.68 0 0 1-71.68-71.68V401.5616a71.68 71.68 0 0 1 71.68-71.68h671.6928a71.68 71.68 0 0 1 71.68 71.68z" fill="#474A54"></path><path d="M756.1216 479.744H271.7184a30.72 30.72 0 0 0 0 61.44h484.4032a30.72 30.72 0 0 0 0-61.44zM611.4304 659.1488H271.7184a30.72 30.72 0 1 0 0 61.44h339.712a30.72 30.72 0 1 0 0-61.44z" fill="#474A54"></path></svg>
                    <svg t="1780672335963" class="icon notice-card-icon-dark" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1707" width="200" height="200"><path d="M837.26 267.28H659l-73.59-123.56c-14.76-24.84-41.66-39.75-71.52-39.91h-0.51c-29.86 0-56.59 14.73-71.69 39.25L366 267.28H190.19c-68.56 0-124.28 53.17-124.28 118.59V802.6c0 65.42 55.72 118.59 124.28 118.59h646.89c68.56 0 124.27-53.17 124.27-118.59V385.87c0.18-65.42-55.54-118.59-124.09-118.59zM190.2 839.78c-23.65 0-42.89-16.69-42.89-37.2v-416.7c0-20.51 19.24-37.18 42.89-37.18h221.52l99.49-163.29 4.53 0.22 97 163.07h224.52c16.89 0 27.47 8.08 32.28 12.89S880 374.41 880 385.68v416.9c0 20.51-19.24 37.2-42.89 37.2z" fill="#949DA6" p-id="1708"></path><path d="M741.75 470.13H312.41c-23.6 0-42.72 18.22-42.72 40.7s19.12 40.7 42.72 40.7h429.34c23.6 0 42.72-18.22 42.72-40.7s-19.12-40.7-42.72-40.7zM577.84 673.64H312.41c-23.6 0-42.72 18.22-42.72 40.7S288.81 755 312.41 755h265.43c23.6 0 42.72-18.22 42.72-40.7s-19.12-40.7-42.72-40.7zM350.82 266.62h325.62v81.4H350.82z" fill="#949DA6" p-id="1709"></path></svg>
                </span>
                <h2 id="sidebar-notice-title">侧栏公告</h2>
            </div>
            <div class="notice-content">
                <?= $announcement['html'] ?? '' ?>
            </div>
        </section>
    <?php endif; ?>
</aside>
