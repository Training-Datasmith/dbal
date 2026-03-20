<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use function db2_conn_error;
use function db2_conn_errormsg;
use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Connection_Error extends Abstract_Exception
{
    /** @param resource $connection */
    public static function new($connection): self
    {
        $message = db2_conn_errormsg($connection);
        $sql_state = db2_conn_error($connection);
        return Factory::create($message, static fn(int $code): self => new self($message, $sql_state, $code));
    }
}