<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use mysqli_sql_exception;
use mysqli_stmt;
use ReflectionProperty;
/** @internal */
final class Statement_Error extends Abstract_Exception
{
    public static function new(mysqli_stmt $statement): self
    {
        return new self($statement->error, $statement->sqlstate, $statement->errno);
    }
    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        return new self($exception->get_message(), $p->get_value($exception), $exception->get_code(), $exception);
    }
}