<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Connection_Exception;
final class No_Active_Transaction extends Connection_Exception
{
    public static function new(): self
    {
        return new self('There is no active transaction.');
    }
}