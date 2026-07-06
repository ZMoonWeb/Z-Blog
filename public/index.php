<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Config;

Config::load(dirname(__DIR__));

$app = new App();
$app->run();
