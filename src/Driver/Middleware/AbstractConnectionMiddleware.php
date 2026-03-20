<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
abstract class Abstract_Connection_Middleware implements Connection
{
    public function __construct(private readonly Connection $wrapped_connection)
    {
    }
    public function prepare(string $sql): Statement
    {
        return $this->wrapped_connection->prepare($sql);
    }
    public function query(string $sql): Result
    {
        return $this->wrapped_connection->query($sql);
    }
    public function quote(string $value): string
    {
        return $this->wrapped_connection->quote($value);
    }
    public function exec(string $sql): int|string
    {
        return $this->wrapped_connection->exec($sql);
    }
    public function last_insert_id(): int|string
    {
        return $this->wrapped_connection->last_insert_id();
    }
    public function begin_transaction(): void
    {
        $this->wrapped_connection->begin_transaction();
    }
    public function commit(): void
    {
        $this->wrapped_connection->commit();
    }
    public function roll_back(): void
    {
        $this->wrapped_connection->roll_back();
    }
    public function get_server_version(): string
    {
        return $this->wrapped_connection->get_server_version();
    }
    /**
     * {@inheritDoc}
     */
    public function get_native_connection()
    {
        return $this->wrapped_connection->get_native_connection();
    }
}