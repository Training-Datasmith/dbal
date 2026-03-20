<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\API\My_Sql;

use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Connection_Lost;
use Doctrine\DBAL\Exception\Database_Does_Not_Exist;
use Doctrine\DBAL\Exception\Deadlock_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Invalid_Field_Name_Exception;
use Doctrine\DBAL\Exception\Lock_Wait_Timeout_Exception;
use Doctrine\DBAL\Exception\Non_Unique_Field_Name_Exception;
use Doctrine\DBAL\Exception\Not_Null_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\Syntax_Error_Exception;
use Doctrine\DBAL\Exception\Table_Exists_Exception;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Query;
use function str_contains;
/** @internal */
final class Exception_Converter implements Exception_Converter_Interface
{
    /**
     * @link https://dev.mysql.com/doc/mysql-errors/8.0/en/client-error-reference.html
     * @link https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html
     */
    public function convert(Exception $exception, ?Query $query): Driver_Exception
    {
        if ($exception->get_code() === 1524 && str_contains($exception->get_message(), 'Plugin \'mysql_native_password\' is not loaded')) {
            // Workaround for MySQL 8.4 if we request an unknown user.
            // https://bugs.mysql.com/bug.php?id=114876
            return new Connection_Exception($exception, $query);
        }
        return match ($exception->get_code()) {
            1008 => new Database_Does_Not_Exist($exception, $query),
            1213 => new Deadlock_Exception($exception, $query),
            1205 => new Lock_Wait_Timeout_Exception($exception, $query),
            1050 => new Table_Exists_Exception($exception, $query),
            1051, 1146 => new Table_Not_Found_Exception($exception, $query),
            1216, 1217, 1451, 1452, 1701 => new Foreign_Key_Constraint_Violation_Exception($exception, $query),
            1062, 1557, 1569, 1586 => new Unique_Constraint_Violation_Exception($exception, $query),
            1054, 1166, 1611 => new Invalid_Field_Name_Exception($exception, $query),
            1052, 1060, 1110 => new Non_Unique_Field_Name_Exception($exception, $query),
            1064, 1149, 1287, 1341, 1342, 1343, 1344, 1382, 1479, 1541, 1554, 1626 => new Syntax_Error_Exception($exception, $query),
            1044, 1045, 1046, 1049, 1095, 1142, 1143, 1227, 1370, 1429, 2002, 2005, 2054 => new Connection_Exception($exception, $query),
            2006, 4031 => new Connection_Lost($exception, $query),
            1048, 1121, 1138, 1171, 1252, 1263, 1364, 1566 => new Not_Null_Constraint_Violation_Exception($exception, $query),
            default => new Driver_Exception($exception, $query),
        };
    }
}