<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

abstract class Migration
{
    abstract public function up(PDO $db): void;

    public function down(PDO $db): void
    {
    }
}
