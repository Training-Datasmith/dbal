<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\IBMDB2\Exception_Converter;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Server_Version_Provider;
/**
 * Abstract base implementation of the {@see Driver} interface for Db2 based drivers.
 */
abstract class Abstract_Db2driver implements Driver
{
    public function get_database_platform(Server_Version_Provider $version_provider): DB2Platform
    {
        return new DB2Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
}