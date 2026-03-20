<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Abstract_Oracle_Driver\Easy_Connect_String;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\OCI\Exception_Converter;
use Doctrine\DBAL\Platforms\Oracle_Platform;
use Doctrine\DBAL\Server_Version_Provider;
/**
 * Abstract base implementation of the {@see Driver} interface for Oracle based drivers.
 */
abstract class Abstract_Oracle_Driver implements Driver
{
    public function get_database_platform(Server_Version_Provider $version_provider): Oracle_Platform
    {
        return new Oracle_Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
    /**
     * Returns an appropriate Easy Connect String for the given parameters.
     *
     * @param array<string, mixed> $params The connection parameters to return the Easy Connect String for.
     */
    protected function get_easy_connect_string(array $params): string
    {
        return (string) Easy_Connect_String::from_connection_parameters($params);
    }
}