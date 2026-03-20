<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Cache\Exception;

use Doctrine\DBAL\Cache\Cache_Exception;
final class No_Cache_Key extends Cache_Exception
{
    public static function new(): self
    {
        return new self('No cache key was set.');
    }
}