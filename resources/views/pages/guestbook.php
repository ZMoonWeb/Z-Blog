<?php
$title = $title ?? '留言板';

ob_start();

$guestbookBaseUrl = '/guestbook';
$guestbookFormAction = '/guestbook';
require dirname(__DIR__) . '/partials/guestbook-content.php';

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
?>
