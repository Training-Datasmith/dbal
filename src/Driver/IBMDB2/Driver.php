<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2;

use function db2_connect;
use function db2_pconnect;
use Doctrine\DBAL\Driver\Abstract_Db2driver;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Connection_Failed;
use Sensitive_Parameter;
final class Driver extends Abstract_Db2driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $data_source_name = Data_Source_Name::from_connection_parameters($params)->to_string();
        $username = $params['user'] ?? '';
        $password = $params['password'] ?? '';
        $driver_options = $params['driverOptions'] ?? [];
        if (!empty($params['persistent'])) {
            $connection = db2_pconnect($data_source_name, $username, $password, $driver_options);
        } else {
            $connection = db2_connect($data_source_name, $username, $password, $driver_options);
        }
        if ($connection === false) {
            throw Connection_Failed::new();
        }
        return new Connection($connection);
    }
}