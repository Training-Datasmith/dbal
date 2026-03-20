<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2;

use function assert;
use function db2_autocommit;
use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;
use function db2_commit;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\No_Identity_Value;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Connection_Error;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Prepare_Failed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Statement_Error;
use function error_get_last;
use stdClass;
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
        $server_info = db2_server_info($this->connection);
        assert($server_info instanceof stdClass);
        return $server_info->DBMS_VER;
    }
    public function prepare(string $sql): Statement
    {
        $stmt = @db2_prepare($this->connection, $sql);
        if ($stmt === false) {
            throw Prepare_Failed::new(error_get_last());
        }
        return new Statement($stmt);
    }
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }
    public function quote(string $value): string
    {
        return "'" . db2_escape_string($value) . "'";
    }
    public function exec(string $sql): int
    {
        $stmt = @db2_exec($this->connection, $sql);
        if ($stmt === false) {
            throw Statement_Error::new();
        }
        $num_rows = db2_num_rows($stmt);
        if ($num_rows === false) {
            throw Statement_Error::new();
        }
        return $num_rows;
    }
    public function last_insert_id(): string
    {
        $last_insert_id = db2_last_insert_id($this->connection);
        if ($last_insert_id === null) {
            throw No_Identity_Value::new();
        }
        return $last_insert_id;
    }
    public function begin_transaction(): void
    {
        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_OFF) !== true) {
            throw Connection_Error::new($this->connection);
        }
    }
    public function commit(): void
    {
        if (!db2_commit($this->connection)) {
            throw Connection_Error::new($this->connection);
        }
        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_ON) !== true) {
            throw Connection_Error::new($this->connection);
        }
    }
    public function roll_back(): void
    {
        if (!db2_rollback($this->connection)) {
            throw Connection_Error::new($this->connection);
        }
        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_ON) !== true) {
            throw Connection_Error::new($this->connection);
        }
    }
    /** @return resource */
    public function get_native_connection()
    {
        return $this->connection;
    }
}