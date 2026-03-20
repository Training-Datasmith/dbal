<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\OCI;

use Doctrine\DBAL\Driver\Abstract_Oracle_Driver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Exception\Invalid_Configuration;
use Doctrine\DBAL\Driver\PDO\Pdo_Connect;
use function is_string;
use PDO;
use PDOException;
use Sensitive_Parameter;
final class Driver extends Abstract_Oracle_Driver
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
            $pdo = $this->do_connect($this->construct_pdo_dsn($params), $params['user'] ?? '', $params['password'] ?? '', $driver_options);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        return new Connection($pdo);
    }
    /**
     * Constructs the Oracle PDO DSN.
     *
     * @param mixed[] $params
     */
    private function construct_pdo_dsn(array $params): string
    {
        $dsn = 'oci:dbname=' . $this->get_easy_connect_string($params);
        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }
        return $dsn;
    }
}