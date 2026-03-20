<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use function assert;
use Doctrine\DBAL\Driver\Abstract_Exception;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;
/** @internal */
final class Connection_Failed extends Abstract_Exception
{
    public static function new(mysqli $connection): self
    {
        $error = $connection->connect_error;
        assert($error !== null);
        return new self($error, 'HY000', $connection->connect_errno);
    }
    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        return new self($exception->get_message(), $p->get_value($exception), $exception->get_code(), $exception);
    }
}