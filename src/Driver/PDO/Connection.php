<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO;

use function assert;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\Identity_Columns_Not_Supported;
use Doctrine\DBAL\Driver\Exception\No_Identity_Value;
use PDO;
use PDOException;
use PDOStatement;
final readonly class Connection implements Connection_Interface
{
    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private PDO $connection)
    {
        $connection->set_attribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function exec(string $sql): int
    {
        try {
            $result = $this->connection->exec($sql);
            assert($result !== false);
            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function get_server_version(): string
    {
        return $this->connection->get_attribute(PDO::ATTR_SERVER_VERSION);
    }
    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            assert($stmt instanceof PDOStatement);
            return new Statement($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function query(string $sql): Result
    {
        try {
            $stmt = $this->connection->query($sql);
            assert($stmt instanceof PDOStatement);
            return new Result($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }
    public function last_insert_id(): int|string
    {
        try {
            $value = $this->connection->last_insert_id();
        } catch (PDOException $exception) {
            assert($exception->error_info !== null);
            [$sql_state] = $exception->error_info;
            // if the PDO driver does not support this capability, PDO::lastInsertId() triggers an IM001 SQLSTATE
            // see https://www.php.net/manual/en/pdo.lastinsertid.php
            if ($sql_state === 'IM001') {
                throw Identity_Columns_Not_Supported::new();
            }
            // PDO PGSQL throws a 'lastval is not yet defined in this session' error when no identity value is
            // available, with SQLSTATE 55000 'Object Not In Prerequisite State'
            if ($sql_state === '55000' && $this->connection->get_attribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                throw No_Identity_Value::new($exception);
            }
            throw Exception::new($exception);
        }
        // pdo_mysql & pdo_sqlite return '0', pdo_sqlsrv returns '' or false depending on the PHP version
        if ($value === '0' || $value === '' || $value === false) {
            throw No_Identity_Value::new();
        }
        return $value;
    }
    public function begin_transaction(): void
    {
        try {
            $this->connection->begin_transaction();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function roll_back(): void
    {
        try {
            $this->connection->roll_back();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function get_native_connection(): PDO
    {
        return $this->connection;
    }
}