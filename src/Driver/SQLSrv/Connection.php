<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sql_Srv;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\No_Identity_Value;
use Doctrine\DBAL\Driver\Sql_Srv\Exception\Error;
use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;
final readonly class Connection implements Connection_Interface
{
    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private mixed $connection)
    {
    }
    public function get_server_version(): string
    {
        $server_info = sqlsrv_server_info($this->connection);
        return $server_info['SQLServerVersion'];
    }
    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection, $sql);
    }
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }
    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
    public function exec(string $sql): int
    {
        $stmt = sqlsrv_query($this->connection, $sql);
        if ($stmt === false) {
            throw Error::new();
        }
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected === false) {
            throw Error::new();
        }
        return $rows_affected;
    }
    public function last_insert_id(): int|string
    {
        $result = $this->query('SELECT SCOPE_IDENTITY()');
        $last_insert_id = $result->fetch_one();
        if ($last_insert_id === null) {
            throw No_Identity_Value::new();
        }
        return $last_insert_id;
    }
    public function begin_transaction(): void
    {
        if (!sqlsrv_begin_transaction($this->connection)) {
            throw Error::new();
        }
    }
    public function commit(): void
    {
        if (!sqlsrv_commit($this->connection)) {
            throw Error::new();
        }
    }
    public function roll_back(): void
    {
        if (!sqlsrv_rollback($this->connection)) {
            throw Error::new();
        }
    }
    /** @return resource */
    public function get_native_connection()
    {
        return $this->connection;
    }
}