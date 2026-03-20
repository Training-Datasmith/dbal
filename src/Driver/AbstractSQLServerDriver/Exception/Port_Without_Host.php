<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Abstract_Sql_Server_Driver\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Port_Without_Host extends Abstract_Exception
{
    public static function new(): self
    {
        return new self('Connection port specified without the host');
    }
}