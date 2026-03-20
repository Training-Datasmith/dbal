<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Abstract_My_Sql_Driver;
use Doctrine\DBAL\Driver\Mysqli\Exception\Connection_Failed;
use Doctrine\DBAL\Driver\Mysqli\Exception\Host_Required;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Charset;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Options;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Secure;
use Generator;
use mysqli;
use mysqli_sql_exception;
use Sensitive_Parameter;
final class Driver extends Abstract_My_Sql_Driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        if (!empty($params['persistent'])) {
            if (!isset($params['host'])) {
                throw Host_Required::for_persistent_connection();
            }
            $host = 'p:' . $params['host'];
        } else {
            $host = $params['host'] ?? '';
        }
        $connection = new mysqli();
        foreach ($this->compile_pre_initializers($params) as $initializer) {
            $initializer->initialize($connection);
        }
        try {
            $success = @$connection->real_connect($host, $params['user'] ?? '', $params['password'] ?? '', $params['dbname'] ?? '', $params['port'] ?? 0, $params['unix_socket'] ?? '', $params['driverOptions'][Connection::OPTION_FLAGS] ?? 0);
        } catch (mysqli_sql_exception $e) {
            throw Connection_Failed::upcast($e);
        }
        if (!$success) {
            throw Connection_Failed::new($connection);
        }
        foreach ($this->compile_post_initializers($params) as $initializer) {
            $initializer->initialize($connection);
        }
        return new Connection($connection);
    }
    /**
     * @param array<string, mixed> $params
     *
     * @return Generator<int, Initializer>
     */
    private function compile_pre_initializers(
        #[Sensitive_Parameter]
        array $params
    ): Generator
    {
        unset($params['driverOptions'][Connection::OPTION_FLAGS]);
        if (isset($params['driverOptions']) && $params['driverOptions'] !== []) {
            yield new Options($params['driverOptions']);
        }
        if (!isset($params['ssl_key']) && !isset($params['ssl_cert']) && !isset($params['ssl_ca']) && !isset($params['ssl_capath']) && !isset($params['ssl_cipher'])) {
            return;
        }
        yield new Secure($params['ssl_key'] ?? '', $params['ssl_cert'] ?? '', $params['ssl_ca'] ?? '', $params['ssl_capath'] ?? '', $params['ssl_cipher'] ?? '');
    }
    /**
     * @param array<string, mixed> $params
     *
     * @return Generator<int, Initializer>
     */
    private function compile_post_initializers(
        #[Sensitive_Parameter]
        array $params
    ): Generator
    {
        if (!isset($params['charset'])) {
            return;
        }
        yield new Charset($params['charset']);
    }
}