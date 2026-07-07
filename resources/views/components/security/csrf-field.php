<?php

declare(strict_types=1);

use App\Core\Security\Csrf;

echo Csrf::field(isset($namespace) && is_string($namespace) ? $namespace : 'default');