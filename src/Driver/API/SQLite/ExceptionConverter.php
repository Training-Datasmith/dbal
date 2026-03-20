<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\Sq_Lite;

use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Invalid_Field_Name_Exception;
use Doctrine\DBAL\Exception\Lock_Wait_Timeout_Exception;
use Doctrine\DBAL\Exception\Non_Unique_Field_Name_Exception;
use Doctrine\DBAL\Exception\Not_Null_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Read_Only_Exception;
use Doctrine\DBAL\Exception\Syntax_Error_Exception;
use Doctrine\DBAL\Exception\Table_Exists_Exception;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Query;
use function str_contains;
/** @internal */
final class Exception_Converter implements Exception_Converter_Interface
{
    /** @link http://www.sqlite.org/c3ref/c_abort.html */
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        if (str_contains($exception->get_message(), 'database is locked')) {
            return new Lock_Wait_Timeout_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'must be unique') || str_contains($exception->get_message(), 'is not unique') || str_contains($exception->get_message(), 'are not unique') || str_contains($exception->get_message(), 'UNIQUE constraint failed')) {
            return new Unique_Constraint_Violation_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'may not be NULL') || str_contains($exception->get_message(), 'NOT NULL constraint failed')) {
            return new Not_Null_Constraint_Violation_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'no such table:')) {
            return new Table_Not_Found_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'already exists')) {
            return new Table_Exists_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'has no column named')) {
            return new Invalid_Field_Name_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'ambiguous column name')) {
            return new Non_Unique_Field_Name_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'syntax error')) {
            return new Syntax_Error_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'attempt to write a readonly database')) {
            return new Read_Only_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'unable to open database file')) {
            return new Connection_Exception($exception, $query);
        }
        if (str_contains($exception->get_message(), 'FOREIGN KEY constraint failed')) {
            return new Foreign_Key_Constraint_Violation_Exception($exception, $query);
        }
        return new Driver_Exception($exception, $query);
    }
}