<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use Doctrine\DBAL\Driver\Abstract_Postgre_Sql_Driver;
use ErrorException;
use function func_get_args;
use function implode;
use function pg_connect;
use const PGSQL_CONNECT_FORCE_NEW;
use function preg_match;
use function restore_error_handler;
use function str_replace;
use Sensitive_Parameter;
use function set_error_handler;
use function sprintf;
final class Driver extends Abstract_Postgre_Sql_Driver
{
    /** {@inheritDoc} */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity, ...array_slice(func_get_args(), 2, 2));
        });
        try {
            $connection = pg_connect($this->construct_connection_string($params), PGSQL_CONNECT_FORCE_NEW);
        } catch (ErrorException $e) {
            throw new Exception($e->get_message(), '08006', 0, $e);
        } finally {
            restore_error_handler();
        }
        if ($connection === false) {
            throw new Exception('Unable to connect to Postgres server.');
        }
        $driver_connection = new Connection($connection);
        if (isset($params['application_name'])) {
            $driver_connection->exec('SET application_name = ' . $driver_connection->quote($params['application_name']));
        }
        return $driver_connection;
    }
    /**
     * Constructs the Postgres connection string
     *
     * @param array<string, mixed> $params
     */
    private function construct_connection_string(
        #[Sensitive_Parameter]
        array $params
    ): string
    {
        // pg_connect used by Doctrine DBAL does not support [...] notation,
        // but requires the host address in plain form like `aa:bb:99...`
        $matches = [];
        if (isset($params['host']) && preg_match('/^\[(.+)\]$/', (string) $params['host'], $matches) === 1) {
            $params['hostaddr'] = $matches[1];
            unset($params['host']);
        }
        $components = array_filter(['host' => $params['host'] ?? null, 'hostaddr' => $params['hostaddr'] ?? null, 'port' => $params['port'] ?? null, 'dbname' => $params['dbname'] ?? 'postgres', 'user' => $params['user'] ?? null, 'password' => $params['password'] ?? null, 'sslmode' => $params['sslmode'] ?? null, 'gssencmode' => $params['gssencmode'] ?? null], static fn(int|string|null $value): bool => $value !== '' && $value !== null);
        return implode(' ', array_map(static fn(int|string $value, string $key): string => sprintf("%s='%s'", $key, str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)), array_values($components), array_keys($components)));
    }
}