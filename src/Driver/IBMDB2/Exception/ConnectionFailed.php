<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use function db2_conn_error;
use function db2_conn_errormsg;
use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Connection_Failed extends Abstract_Exception
{
    public static function new(): self
    {
        $message = db2_conn_errormsg();
        $sql_state = db2_conn_error();
        return Factory::create($message, static fn(int $code): self => new self($message, $sql_state, $code));
    }
}