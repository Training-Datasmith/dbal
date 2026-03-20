<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sq_Lite3;

use function assert;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\No_Identity_Value;
use function sprintf;
use Sq_Lite3;
final readonly class Connection implements Connection_Interface
{
    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private Sq_Lite3 $connection)
    {
    }
    public function prepare(string $sql): Statement
    {
        try {
            $statement = $this->connection->prepare($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        assert($statement !== false);
        return new Statement($this->connection, $statement);
    }
    public function query(string $sql): Result
    {
        try {
            $result = $this->connection->query($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        assert($result !== false);
        return new Result($result, $this->connection->changes());
    }
    public function quote(string $value): string
    {
        return sprintf('\'%s\'', Sq_Lite3::escape_string($value));
    }
    public function exec(string $sql): int
    {
        try {
            $this->connection->exec($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        return $this->connection->changes();
    }
    public function last_insert_id(): int
    {
        $value = $this->connection->last_insert_row_id();
        if ($value === 0) {
            throw No_Identity_Value::new();
        }
        return $value;
    }
    public function begin_transaction(): void
    {
        try {
            $this->connection->exec('BEGIN');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }
    public function commit(): void
    {
        try {
            $this->connection->exec('COMMIT');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }
    public function roll_back(): void
    {
        try {
            $this->connection->exec('ROLLBACK');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }
    public function get_native_connection(): Sq_Lite3
    {
        return $this->connection;
    }
    public function get_server_version(): string
    {
        return Sq_Lite3::version()['versionString'];
    }
}