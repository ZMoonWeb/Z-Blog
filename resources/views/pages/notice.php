<?php
$title = $title ?? '公告';
$announcements = $announcements ?? [];

ob_start();

require dirname(__DIR__) . '/partials/notice-list.php';

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
?>
