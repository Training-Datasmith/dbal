<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Psr\Log\Logger_Interface;
final readonly class Middleware implements Middleware_Interface
{
    public function __construct(private Logger_Interface $logger)
    {
    }
    public function wrap(Driver_Interface $driver): Driver_Interface
    {
        return new Driver($driver, $this->logger);
    }
}