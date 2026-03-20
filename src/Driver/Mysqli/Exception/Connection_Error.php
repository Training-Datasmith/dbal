<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;
/** @internal */
final class Connection_Error extends Abstract_Exception
{
    public static function new(mysqli $connection): self
    {
        return new self($connection->error, $connection->sqlstate, $connection->errno);
    }
    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        return new self($exception->get_message(), $p->get_value($exception), $exception->get_code(), $exception);
    }
}