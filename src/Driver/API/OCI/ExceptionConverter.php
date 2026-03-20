<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\OCI;

use function assert;
use function count;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\PDO\Exception as DriverPDOException;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Database_Does_Not_Exist;
use Doctrine\DBAL\Exception\Database_Object_Not_Found_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Invalid_Field_Name_Exception;
use Doctrine\DBAL\Exception\Non_Unique_Field_Name_Exception;
use Doctrine\DBAL\Exception\Not_Null_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Syntax_Error_Exception;
use Doctrine\DBAL\Exception\Table_Exists_Exception;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Exception\Transaction_Rolled_Back;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Query;
use function explode;
use function str_replace;
/** @internal */
final class Exception_Converter implements Exception_Converter_Interface
{
    /** @link http://www.dba-oracle.com/t_error_code_list.htm */
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        return match ($exception->get_code()) {
            1, 2299, 38911 => new Unique_Constraint_Violation_Exception($exception, $query),
            904 => new Invalid_Field_Name_Exception($exception, $query),
            918, 960 => new Non_Unique_Field_Name_Exception($exception, $query),
            923 => new Syntax_Error_Exception($exception, $query),
            942 => new Table_Not_Found_Exception($exception, $query),
            955 => new Table_Exists_Exception($exception, $query),
            1017, 12545 => new Connection_Exception($exception, $query),
            1400 => new Not_Null_Constraint_Violation_Exception($exception, $query),
            1918 => new Database_Does_Not_Exist($exception, $query),
            2091 => (function () use ($exception, $query): \Doctrine\DBAL\Exception\Transaction_Rolled_Back {
                //SQLSTATE[HY000]: General error: 2091 OCITransCommit: ORA-02091: transaction rolled back
                //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
                $lines = explode("\n", $exception->get_message(), 2);
                assert(count($lines) >= 2);
                [, $cause_error] = $lines;
                [$cause_code] = explode(': ', $cause_error, 2);
                $code = (int) str_replace('ORA-', '', $cause_code);
                $sql_state = $exception->get_sql_state();
                if ($exception instanceof Driver_Pdo_Exception) {
                    $why = $this->convert(new Driver_Pdo_Exception($cause_error, $sql_state, $code, $exception), $query);
                } else {
                    $why = $this->convert(new Error($cause_error, $sql_state, $code, $exception), $query);
                }
                return new Transaction_Rolled_Back($why, $query);
            })(),
            2289, 2443, 4080 => new Database_Object_Not_Found_Exception($exception, $query),
            2266, 2291, 2292 => new Foreign_Key_Constraint_Violation_Exception($exception, $query),
            default => new Driver_Exception($exception, $query),
        };
    }
}