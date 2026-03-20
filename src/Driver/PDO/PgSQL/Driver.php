<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Pg_Sql;

use Doctrine\DBAL\Driver\Abstract_Postgre_Sql_Driver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Exception\Invalid_Configuration;
use Doctrine\DBAL\Driver\PDO\Pdo_Connect;
use function is_string;
use PDO;
use Pdo\Pgsql;
use PDOException;
use const PHP_VERSION_ID;
use Sensitive_Parameter;
final class Driver extends Abstract_Postgre_Sql_Driver
{
    use Pdo_Connect;
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $driver_options = $params['driverOptions'] ?? [];
        if (!empty($params['persistent'])) {
            $driver_options[PDO::ATTR_PERSISTENT] = true;
        }
        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && !is_string($params[$key])) {
                throw Invalid_Configuration::not_a_string_or_null($key, $params[$key]);
            }
        }
        $safe_params = $params;
        unset($safe_params['password']);
        try {
            $pdo = $this->do_connect($this->construct_pdo_dsn($safe_params), $params['user'] ?? '', $params['password'] ?? '', $driver_options);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        $disable_prepares_attr = PHP_VERSION_ID >= 80400 ? Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES;
        if (!isset($driver_options[$disable_prepares_attr]) || $driver_options[$disable_prepares_attr] === true) {
            $pdo->set_attribute($disable_prepares_attr, true);
        }
        $connection = new Connection($pdo);
        /* defining client_encoding via SET NAMES to avoid inconsistent DSN support
         * - passing client_encoding via the 'options' param breaks pgbouncer support
         */
        if (isset($params['charset'])) {
            $connection->exec('SET NAMES \'' . $params['charset'] . '\'');
        }
        return $connection;
    }
    /**
     * Constructs the Postgres PDO DSN.
     *
     * @param array<string, mixed> $params
     */
    private function construct_pdo_dsn(array $params): string
    {
        $dsn = 'pgsql:';
        if (isset($params['host']) && $params['host'] !== '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port']) && $params['port'] !== '') {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }
        if (isset($params['sslmode'])) {
            $dsn .= 'sslmode=' . $params['sslmode'] . ';';
        }
        if (isset($params['sslrootcert'])) {
            $dsn .= 'sslrootcert=' . $params['sslrootcert'] . ';';
        }
        if (isset($params['sslcert'])) {
            $dsn .= 'sslcert=' . $params['sslcert'] . ';';
        }
        if (isset($params['sslkey'])) {
            $dsn .= 'sslkey=' . $params['sslkey'] . ';';
        }
        if (isset($params['sslcrl'])) {
            $dsn .= 'sslcrl=' . $params['sslcrl'] . ';';
        }
        if (isset($params['application_name'])) {
            $dsn .= 'application_name=' . $params['application_name'] . ';';
        }
        if (isset($params['gssencmode'])) {
            $dsn .= 'gssencmode=' . $params['gssencmode'] . ';';
        }
        return $dsn;
    }
}