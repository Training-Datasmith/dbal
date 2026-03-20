<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;
use function sprintf;
/** @internal */
final class Invalid_Charset extends Abstract_Exception
{
    public static function from_charset(mysqli $connection, string $charset): self
    {
        return new self(sprintf('Failed to set charset "%s": %s', $charset, $connection->error), $connection->sqlstate, $connection->errno);
    }
    public static function upcast(mysqli_sql_exception $exception, string $charset): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        return new self(sprintf('Failed to set charset "%s": %s', $charset, $exception->get_message()), $p->get_value($exception), $exception->get_code(), $exception);
    }
}