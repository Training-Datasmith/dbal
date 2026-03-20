<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\Sql_Srv;

use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Database_Object_Not_Found_Exception;
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
 * @link https://docs.microsoft.com/en-us/sql/relational-databases/errors-events/database-engine-events-and-errors
 */
final class Exception_Converter implements Exception_Converter_Interface
{
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        return match ($exception->get_code()) {
            102 => new Syntax_Error_Exception($exception, $query),
            207 => new Invalid_Field_Name_Exception($exception, $query),
            208 => new Table_Not_Found_Exception($exception, $query),
            209 => new Non_Unique_Field_Name_Exception($exception, $query),
            515 => new Not_Null_Constraint_Violation_Exception($exception, $query),
            547, 4712 => new Foreign_Key_Constraint_Violation_Exception($exception, $query),
            2601, 2627 => new Unique_Constraint_Violation_Exception($exception, $query),
            2714 => new Table_Exists_Exception($exception, $query),
            3701, 15151 => new Database_Object_Not_Found_Exception($exception, $query),
            11001, 18456 => new Connection_Exception($exception, $query),
            default => new Driver_Exception($exception, $query),
        };
    }
}