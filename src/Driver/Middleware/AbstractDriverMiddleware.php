<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Server_Version_Provider;
use Sensitive_Parameter;
abstract class Abstract_Driver_Middleware implements Driver
{
    public function __construct(private readonly Driver $wrapped_driver)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Driver_Connection
    {
        return $this->wrapped_driver->connect($params);
    }
    public function get_database_platform(Server_Version_Provider $version_provider): Abstract_Platform
    {
        return $this->wrapped_driver->get_database_platform($version_provider);
    }
    public function get_exception_converter(): Exception_Converter
    {
        return $this->wrapped_driver->get_exception_converter();
    }
}