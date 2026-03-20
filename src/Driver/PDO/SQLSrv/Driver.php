<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Sql_Srv;

use Doctrine\DBAL\Driver\Abstract_Sql_Server_Driver;
use Doctrine\DBAL\Driver\Abstract_Sql_Server_Driver\Exception\Port_Without_Host;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Driver\PDO\Exception\Invalid_Configuration;
use Doctrine\DBAL\Driver\PDO\Pdo_Connect;
use function is_int;
use function is_string;
use PDO;
use Sensitive_Parameter;
use function sprintf;
final class Driver extends Abstract_Sql_Server_Driver
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
        $driver_options = $dsn_options = [];
        if (isset($params['driverOptions'])) {
            foreach ($params['driverOptions'] as $option => $value) {
                if (is_int($option)) {
                    $driver_options[$option] = $value;
                } else {
                    $dsn_options[$option] = $value;
                }
            }
        }
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
            $pdo = $this->do_connect($this->construct_dsn($safe_params, $dsn_options), $params['user'] ?? '', $params['password'] ?? '', $driver_options);
        } catch (\PDOException $exception) {
            throw PDOException::new($exception);
        }
        return new Connection(new Pdo_Connection($pdo));
    }
    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param mixed[]  $params
     * @param string[] $connectionOptions
     *
     * @throws Exception
     */
    private function construct_dsn(array $params, array $connection_options): string
    {
        $dsn = 'sqlsrv:server=';
        if (isset($params['host'])) {
            $dsn .= $params['host'];
            if (isset($params['port'])) {
                $dsn .= ',' . $params['port'];
            }
        } elseif (isset($params['port'])) {
            throw Port_Without_Host::new();
        }
        if (isset($params['dbname'])) {
            $connection_options['Database'] = $params['dbname'];
        }
        if (isset($params['MultipleActiveResultSets'])) {
            $connection_options['MultipleActiveResultSets'] = $params['MultipleActiveResultSets'] ? 'true' : 'false';
        }
        return $dsn . $this->get_connection_options_dsn($connection_options);
    }
    /**
     * Converts a connection options array to the DSN
     *
     * @param string[] $connectionOptions
     */
    private function get_connection_options_dsn(array $connection_options): string
    {
        $connection_options_dsn = '';
        foreach ($connection_options as $param_name => $param_value) {
            $connection_options_dsn .= sprintf(';%s=%s', $param_name, $param_value);
        }
        return $connection_options_dsn;
    }
}