<?php
declare(strict_types=1);

use Brassica\Core\Database;

function get_db(): PDO
{
    return Database::connection();
}
