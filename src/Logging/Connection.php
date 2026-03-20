<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\Abstract_Connection_Middleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Psr\Log\Logger_Interface;
final class Connection extends Abstract_Connection_Middleware
{
    /** @internal This connection can be only instantiated by its driver. */
    public function __construct(Connection_Interface $connection, private readonly Logger_Interface $logger)
    {
        parent::__construct($connection);
    }
    public function __destruct()
    {
        $this->logger->info('Disconnecting');
    }
    public function prepare(string $sql): Driver_Statement
    {
        return new Statement(parent::prepare($sql), $this->logger, $sql);
    }
    public function query(string $sql): Result
    {
        $this->logger->debug('Executing query: {sql}', ['sql' => $sql]);
        return parent::query($sql);
    }
    public function exec(string $sql): int|string
    {
        $this->logger->debug('Executing statement: {sql}', ['sql' => $sql]);
        return parent::exec($sql);
    }
    public function begin_transaction(): void
    {
        $this->logger->debug('Beginning transaction');
        parent::begin_transaction();
    }
    public function commit(): void
    {
        $this->logger->debug('Committing transaction');
        parent::commit();
    }
    public function roll_back(): void
    {
        $this->logger->debug('Rolling back transaction');
        parent::roll_back();
    }
}