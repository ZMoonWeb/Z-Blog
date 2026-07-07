<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Config;
use App\Core\Http\Request;

$basePath = dirname(__DIR__);
Config::load($basePath);

$request = Request::capture();
$app = new App($basePath);
$app->handle($request)->send();
