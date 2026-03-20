<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\Connection_Error;
use mysqli;
use mysqli_sql_exception;
final readonly class Connection implements Connection_Interface
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';
    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private mysqli $connection)
    {
    }
    public function get_server_version(): string
    {
        return $this->connection->get_server_info();
    }
    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->connection->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            throw Connection_Error::upcast($e);
        }
        if ($stmt === false) {
            throw Connection_Error::new($this->connection);
        }
        return new Statement($stmt);
    }
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }
    public function quote(string $value): string
    {
        return "'" . $this->connection->escape_string($value) . "'";
    }
    public function exec(string $sql): int|string
    {
        try {
            $result = $this->connection->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw Connection_Error::upcast($e);
        }
        if ($result === false) {
            throw Connection_Error::new($this->connection);
        }
        return $this->connection->affected_rows;
    }
    public function last_insert_id(): int|string
    {
        $last_insert_id = $this->connection->insert_id;
        if ($last_insert_id === 0) {
            throw Exception\No_Identity_Value::new();
        }
        return $this->connection->insert_id;
    }
    public function begin_transaction(): void
    {
        try {
            if (!$this->connection->begin_transaction()) {
                throw Connection_Error::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw Connection_Error::upcast($e);
        }
    }
    public function commit(): void
    {
        try {
            if (!$this->connection->commit()) {
                throw Connection_Error::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw Connection_Error::upcast($e);
        }
    }
    public function roll_back(): void
    {
        try {
            if (!$this->connection->rollback()) {
                throw Connection_Error::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw Connection_Error::upcast($e);
        }
    }
    public function get_native_connection(): mysqli
    {
        return $this->connection;
    }
}