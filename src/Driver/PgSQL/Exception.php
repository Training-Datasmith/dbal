<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function pg_result_error_field;
use Pg_Sql\Result as PgSqlResult;
use const PGSQL_DIAG_MESSAGE_PRIMARY;
use const PGSQL_DIAG_SQLSTATE;
/** @internal */
final class Exception extends Abstract_Exception
{
    public static function from_result(Pg_Sql_Result $result): self
    {
        $sqlstate = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
        if ($sqlstate === false) {
            $sqlstate = null;
        }
        return new self((string) pg_result_error_field($result, PGSQL_DIAG_MESSAGE_PRIMARY), $sqlstate);
    }
}