<?php

declare(strict_types=1);

namespace Doctrine\StaticAnalysis\DBAL;

use Doctrine\DBAL\DriverManager;

use function getenv;

use function in_array;

use RuntimeException;

$driver = getenv('DB_DRIVER');
if (! in_array($driver, DriverManager::getAvailableDrivers(), true)) {
    throw new RuntimeException('Not a valid driver');
}

DriverManager::getConnection(['driver' => $driver]);
