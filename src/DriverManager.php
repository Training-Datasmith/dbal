<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use function array_keys;
use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\Pg_Sql;
use Doctrine\DBAL\Driver\Sq_Lite3;
use Doctrine\DBAL\Driver\Sql_Srv;
use Doctrine\DBAL\Exception\Driver_Required;
use Doctrine\DBAL\Exception\Invalid_Driver_Class;
use Doctrine\DBAL\Exception\Invalid_Wrapper_Class;
use Doctrine\DBAL\Exception\Unknown_Driver;
use function is_a;
use Sensitive_Parameter;
/**
 * Factory for creating {@see Connection} instances.
 *
 * @phpstan-type OverrideParams = array{
 *     application_name?: string,
 *     charset?: string,
 *     dbname?: string,
 *     defaultTableOptions?: array<string, mixed>,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     persistent?: bool,
 *     port?: int,
 *     serverVersion?: string,
 *     sessionMode?: int,
 *     user?: string,
 *     unix_socket?: string,
 *     wrapperClass?: class-string<Connection>,
 * }
 * @phpstan-type Params = array{
 *     application_name?: string,
 *     charset?: string,
 *     dbname?: string,
 *     defaultTableOptions?: array<string, mixed>,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     keepReplica?: bool,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     persistent?: bool,
 *     port?: int,
 *     primary?: OverrideParams,
 *     replica?: array<OverrideParams>,
 *     serverVersion?: string,
 *     sessionMode?: int,
 *     user?: string,
 *     wrapperClass?: class-string<Connection>,
 *     unix_socket?: string,
 * }
 */
final class Driver_Manager
{
    /**
     * List of supported drivers and their mappings to the driver classes.
     *
     * To add your own driver use the 'driverClass' parameter to {@see DriverManager::getConnection()}.
     */
    private const DRIVER_MAP = ['pdo_mysql' => PDO\My_Sql\Driver::class, 'pdo_sqlite' => PDO\Sq_Lite\Driver::class, 'pdo_pgsql' => PDO\Pg_Sql\Driver::class, 'pdo_oci' => PDO\OCI\Driver::class, 'oci8' => OCI8\Driver::class, 'ibm_db2' => IBMDB2\Driver::class, 'pdo_sqlsrv' => PDO\Sql_Srv\Driver::class, 'mysqli' => Mysqli\Driver::class, 'pgsql' => Pg_Sql\Driver::class, 'sqlsrv' => Sql_Srv\Driver::class, 'sqlite3' => Sq_Lite3\Driver::class];
    /**
     * Private constructor. This class cannot be instantiated.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
    /**
     * Creates a connection object based on the specified parameters.
     * This method returns a Doctrine\DBAL\Connection which wraps the underlying
     * driver connection.
     *
     * $params must contain at least one of the following.
     *
     * Either 'driver' with one of the array keys of {@see DRIVER_MAP},
     * OR 'driverClass' that contains the full class name (with namespace) of the
     * driver class to instantiate.
     *
     * Other (optional) parameters:
     *
     * <b>user (string)</b>:
     * The username to use when connecting.
     *
     * <b>password (string)</b>:
     * The password to use when connecting.
     *
     * <b>driverOptions (array)</b>:
     * Any additional driver-specific options for the driver. These are just passed
     * through to the driver.
     *
     * <b>wrapperClass</b>:
     * You may specify a custom wrapper class through the 'wrapperClass'
     * parameter but this class MUST inherit from Doctrine\DBAL\Connection.
     *
     * <b>driverClass</b>:
     * The driver class to use.
     *
     * @param Configuration|null $config The configuration to use.
     * @phpstan-param Params $params
     *
     * @phpstan-return ($params is array{wrapperClass: class-string<T>} ? T : Connection)
     *
     * @template T of Connection
     */
    public static function get_connection(
        #[Sensitive_Parameter]
        array $params,
        ?Configuration $config = null
    ): Connection
    {
        $config ??= new Configuration();
        $driver = self::create_driver($params['driver'] ?? null, $params['driverClass'] ?? null);
        foreach ($config->get_middlewares() as $middleware) {
            $driver = $middleware->wrap($driver);
        }
        /** @var class-string<Connection> $wrapperClass */
        $wrapper_class = $params['wrapperClass'] ?? Connection::class;
        if (!is_a($wrapper_class, Connection::class, true)) {
            throw Invalid_Wrapper_Class::new($wrapper_class);
        }
        return new $wrapper_class($params, $driver, $config);
    }
    /**
     * Returns the list of supported drivers.
     *
     * @return string[]
     * @phpstan-return list<key-of<self::DRIVER_MAP>>
     */
    public static function get_available_drivers(): array
    {
        return array_keys(self::DRIVER_MAP);
    }
    /**
     * @param class-string<Driver>|null     $driverClass
     * @param key-of<self::DRIVER_MAP>|null $driver
     */
    private static function create_driver(?string $driver, ?string $driver_class): Driver
    {
        if ($driver_class === null) {
            if ($driver === null) {
                throw Driver_Required::new();
            }
            if (!isset(self::DRIVER_MAP[$driver])) {
                throw Unknown_Driver::new($driver, array_keys(self::DRIVER_MAP));
            }
            $driver_class = self::DRIVER_MAP[$driver];
        } elseif (!is_a($driver_class, Driver::class, true)) {
            throw Invalid_Driver_Class::new($driver_class);
        }
        return new $driver_class();
    }
}