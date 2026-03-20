<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sql_Srv;

use Doctrine\DBAL\Driver\Abstract_Sql_Server_Driver;
use Doctrine\DBAL\Driver\Abstract_Sql_Server_Driver\Exception\Port_Without_Host;
use Doctrine\DBAL\Driver\Sql_Srv\Exception\Error;
use Sensitive_Parameter;
use function sqlsrv_configure;
use function sqlsrv_connect;
/**
 * Driver for ext/sqlsrv.
 */
final class Driver extends Abstract_Sql_Server_Driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $server_name = '';
        if (isset($params['host'])) {
            $server_name = $params['host'];
            if (isset($params['port'])) {
                $server_name .= ',' . $params['port'];
            }
        } elseif (isset($params['port'])) {
            throw Port_Without_Host::new();
        }
        $driver_options = $params['driverOptions'] ?? [];
        if (isset($params['dbname'])) {
            $driver_options['Database'] = $params['dbname'];
        }
        if (isset($params['charset'])) {
            $driver_options['CharacterSet'] = $params['charset'];
        }
        if (isset($params['user'])) {
            $driver_options['UID'] = $params['user'];
        }
        if (isset($params['password'])) {
            $driver_options['PWD'] = $params['password'];
        }
        if (!isset($driver_options['ReturnDatesAsStrings'])) {
            $driver_options['ReturnDatesAsStrings'] = 1;
        }
        if (!sqlsrv_configure('WarningsReturnAsErrors', 0)) {
            throw Error::new();
        }
        $connection = sqlsrv_connect($server_name, $driver_options);
        if ($connection === false) {
            throw Error::new();
        }
        return new Connection($connection);
    }
}