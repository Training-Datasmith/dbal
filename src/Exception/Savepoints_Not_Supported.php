<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Connection_Exception;
final class Savepoints_Not_Supported extends Connection_Exception
{
    public static function new(): self
    {
        return new self('Savepoints are not supported by this driver.');
    }
}