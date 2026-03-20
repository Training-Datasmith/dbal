<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Sq_Lite;

use function array_intersect_key;
use Doctrine\DBAL\Driver\Abstract_Sq_Lite_Driver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Exception\Invalid_Configuration;
use Doctrine\DBAL\Driver\PDO\Pdo_Connect;
use function is_string;
use PDOException;
use Sensitive_Parameter;
final class Driver extends Abstract_Sq_Lite_Driver
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
        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && !is_string($params[$key])) {
                throw Invalid_Configuration::not_a_string_or_null($key, $params[$key]);
            }
        }
        try {
            $pdo = $this->do_connect($this->construct_pdo_dsn(array_intersect_key($params, ['path' => true, 'memory' => true])), $params['user'] ?? '', $params['password'] ?? '', $params['driverOptions'] ?? []);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        return new Connection($pdo);
    }
    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param array<string, mixed> $params
     */
    private function construct_pdo_dsn(array $params): string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }
        return $dsn;
    }
}