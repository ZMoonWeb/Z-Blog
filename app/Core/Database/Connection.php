<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

class Connection
{
    public static function get(): PDO
    {
        return \App\Core\Database::getInstance();
    }

    public static function instance(): PDO
    {
        return self::get();
    }
}
