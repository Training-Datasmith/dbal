<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\API\Exception_Converter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Platforms\Exception\Platform_Exception;
use Sensitive_Parameter;
/**
 * Driver interface.
 * Interface that all DBAL drivers must implement.
 *
 * @phpstan-import-type Params from DriverManager
 */
interface Driver
{
    /**
     * Attempts to create a connection with the database.
     *
     * @param array<string, mixed> $params All connection parameters.
     * @phpstan-param Params $params All connection parameters.
     *
     * @return DriverConnection The database connection.
     *
     * @throws Exception
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Driver_Connection;
    /**
     * Gets the DatabasePlatform instance that provides all the metadata about
     * the platform this driver connects to.
     *
     * @return AbstractPlatform The database platform.
     *
     * @throws PlatformException
     */
    public function get_database_platform(Server_Version_Provider $version_provider): Abstract_Platform;
    /**
     * Gets the ExceptionConverter that can be used to convert driver-level exceptions into DBAL exceptions.
     */
    public function get_exception_converter(): Exception_Converter;
}