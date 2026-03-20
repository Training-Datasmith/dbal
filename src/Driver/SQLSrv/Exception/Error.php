<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sql_Srv\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function rtrim;
use const SQLSRV_ERR_ERRORS;
use function sqlsrv_errors;
/** @internal */
final class Error extends Abstract_Exception
{
    public static function new(): self
    {
        $message = '';
        $sql_state = null;
        $code = 0;
        foreach ((array) sqlsrv_errors(SQLSRV_ERR_ERRORS) as $error) {
            $message .= 'SQLSTATE [' . $error['SQLSTATE'] . ', ' . $error['code'] . ']: ' . $error['message'] . "\n";
            $sql_state ??= $error['SQLSTATE'];
            if ($code !== 0) {
                continue;
            }
            $code = $error['code'];
        }
        if ($message === '') {
            $message = 'SQL Server error occurred but no error message was retrieved from driver.';
        }
        return new self(rtrim($message), $sql_state, $code);
    }
}