<?php
$title = $title ?? '关于本站';

ob_start();

require dirname(__DIR__) . '/partials/about-content.php';

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
?>
