<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Abstract_Sq_Lite_Driver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\Abstract_Driver_Middleware;
use Sensitive_Parameter;
final class Enable_Foreign_Keys implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends Abstract_Driver_Middleware
        {
            /**
             * {@inheritDoc}
             */
            public function connect(
                #[Sensitive_Parameter]
                array $params
            ): Connection
            {
                $connection = parent::connect($params);
                $connection->exec('PRAGMA foreign_keys=ON');
                return $connection;
            }
        };
    }
}