<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\Sq_Lite\Exception_Converter;
use Doctrine\DBAL\Platforms\Sq_Lite_Platform;
use Doctrine\DBAL\Server_Version_Provider;
/**
 * Abstract base implementation of the {@see Driver} interface for SQLite based drivers.
 */
abstract class Abstract_Sq_Lite_Driver implements Driver
{
    public function get_database_platform(Server_Version_Provider $version_provider): Sq_Lite_Platform
    {
        return new Sq_Lite_Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
}