<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\Postgre_Sql;

use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Connection_Lost;
use Doctrine\DBAL\Exception\Database_Does_Not_Exist;
use Doctrine\DBAL\Exception\Deadlock_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Invalid_Field_Name_Exception;
use Doctrine\DBAL\Exception\Non_Unique_Field_Name_Exception;
use Doctrine\DBAL\Exception\Not_Null_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Schema_Does_Not_Exist;
use Doctrine\DBAL\Exception\Syntax_Error_Exception;
use Doctrine\DBAL\Exception\Table_Exists_Exception;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Query;
use function str_contains;
/** @internal */
final class Exception_Converter implements Exception_Converter_Interface
{
    /** @link http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html */
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        switch ($exception->get_sql_state()) {
            case '40001':
            case '40P01':
                return new Deadlock_Exception($exception, $query);
            case '0A000':
                // Foreign key constraint violations during a TRUNCATE operation
                // are considered "feature not supported" in PostgreSQL.
                if (str_contains($exception->get_message(), 'truncate')) {
                    return new Foreign_Key_Constraint_Violation_Exception($exception, $query);
                }
                break;
            case '23502':
                return new Not_Null_Constraint_Violation_Exception($exception, $query);
            case '23503':
                return new Foreign_Key_Constraint_Violation_Exception($exception, $query);
            case '23505':
                return new Unique_Constraint_Violation_Exception($exception, $query);
            case '3D000':
                return new Database_Does_Not_Exist($exception, $query);
            case '3F000':
                return new Schema_Does_Not_Exist($exception, $query);
            case '42601':
                return new Syntax_Error_Exception($exception, $query);
            case '42702':
                return new Non_Unique_Field_Name_Exception($exception, $query);
            case '42703':
                return new Invalid_Field_Name_Exception($exception, $query);
            case '42P01':
                return new Table_Not_Found_Exception($exception, $query);
            case '42P07':
                return new Table_Exists_Exception($exception, $query);
            case '08006':
                return new Connection_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'terminating connection')) {
            return new Connection_Lost($exception, $query);
        }
        return new Driver_Exception($exception, $query);
    }
}