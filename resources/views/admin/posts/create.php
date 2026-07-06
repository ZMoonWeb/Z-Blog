<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>写文章 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-post-editor-page">
    <?php
    $admin = $admin ?? null;
    $post = $post ?? [];
    $categories = $categories ?? [];
    $errors = $errors ?? [];
    ?>

    <div class="admin-layout">
        <?php
        $active = 'posts';
        require __DIR__ . '/../partials/sidebar.php';
        ?>

        <main class="admin-main">
            <?php
            $formAction = '/admin/posts/create';
            $isEdit = false;
            require __DIR__ . '/_form.php';
            ?>
        </main>
    </div>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>
