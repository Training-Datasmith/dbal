<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Cache\Exception;

use Doctrine\DBAL\Cache\Cache_Exception;
final class No_Result_Driver_Configured extends Cache_Exception
{
    public static function new(): self
    {
        return new self('Trying to cache a query but no result driver is configured.');
    }
}