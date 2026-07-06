<?php
$publicPath = dirname(__DIR__, 3) . '/public';
$cssVersion = @filemtime($publicPath . '/assets/css/common/error.css') ?: time();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - 服务器错误</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/common/error.css?v=<?= $cssVersion ?>">
</head>
<body>
    <div class="container">
        <h1>500</h1>
        <p>服务器发生了内部错误，请稍后再试。</p>
        <a href="/">返回首页</a>
    </div>
</body>
</html>
