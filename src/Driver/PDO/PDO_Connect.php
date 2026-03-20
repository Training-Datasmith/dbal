<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO;

use PDO;
use const PHP_VERSION_ID;
use Sensitive_Parameter;
/** @internal */
trait Pdo_Connect
{
    /** @param array<int, mixed> $options */
    private function do_connect(
        #[Sensitive_Parameter]
        string $dsn,
        string $username,
        #[Sensitive_Parameter]
        string $password,
        array $options
    ): PDO
    {
        if (PHP_VERSION_ID < 80400) {
            return new PDO($dsn, $username, $password, $options);
        }
        return PDO::connect($dsn, $username, $password, $options);
    }
}