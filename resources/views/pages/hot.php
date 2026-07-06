<?php
$title = $title ?? '热榜';
$hotPosts = $hotPosts ?? [];

ob_start();

require dirname(__DIR__) . '/partials/hot-ranking.php';

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
?>
