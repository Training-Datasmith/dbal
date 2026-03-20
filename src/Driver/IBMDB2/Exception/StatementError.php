<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use function db2_stmt_error;
use function db2_stmt_errormsg;
use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Statement_Error extends Abstract_Exception
{
    /** @param resource|null $statement */
    public static function new($statement = null): self
    {
        if ($statement !== null) {
            $message = db2_stmt_errormsg($statement);
            $sql_state = db2_stmt_error($statement);
        } else {
            $message = db2_stmt_errormsg();
            $sql_state = db2_stmt_error();
        }
        return Factory::create($message, static fn(int $code): self => new self($message, $sql_state, $code));
    }
}