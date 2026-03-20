<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\IBMDB2;

use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Invalid_Field_Name_Exception;
use Doctrine\DBAL\Exception\Non_Unique_Field_Name_Exception;
use Doctrine\DBAL\Exception\Not_Null_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Syntax_Error_Exception;
use Doctrine\DBAL\Exception\Table_Exists_Exception;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Query;
/**
 * @internal
 *
 * @link https://www.ibm.com/docs/en/db2/11.5?topic=messages-sql
 */
final class Exception_Converter implements Exception_Converter_Interface
{
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        return match ($exception->get_code()) {
            -104 => new Syntax_Error_Exception($exception, $query),
            -203 => new Non_Unique_Field_Name_Exception($exception, $query),
            -204 => new Table_Not_Found_Exception($exception, $query),
            -206 => new Invalid_Field_Name_Exception($exception, $query),
            -407 => new Not_Null_Constraint_Violation_Exception($exception, $query),
            -530, -531, -532, -20356 => new Foreign_Key_Constraint_Violation_Exception($exception, $query),
            -601 => new Table_Exists_Exception($exception, $query),
            -803 => new Unique_Constraint_Violation_Exception($exception, $query),
            -1336, -30082 => new Connection_Exception($exception, $query),
            default => new Driver_Exception($exception, $query),
        };
    }
}