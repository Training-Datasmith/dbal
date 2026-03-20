<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\Sql_Srv\Exception_Converter;
use Doctrine\DBAL\Platforms\Sql_Server_Platform;
use Doctrine\DBAL\Server_Version_Provider;
/**
 * Abstract base implementation of the {@see Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class Abstract_Sql_Server_Driver implements Driver
{
    public function get_database_platform(Server_Version_Provider $version_provider): Sql_Server_Platform
    {
        return new Sql_Server_Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
}