<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\Abstract_Driver_Middleware;
use Sensitive_Parameter;
final class Initialize_Session implements Middleware
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
                $connection->exec('ALTER SESSION SET' . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'" . " NLS_TIME_FORMAT = 'HH24:MI:SS'" . " NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'" . " NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS TZH:TZM'" . " NLS_NUMERIC_CHARACTERS = '.,'");
                return $connection;
            }
        };
    }
}