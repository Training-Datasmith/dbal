<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Connection_Exception;
final class Commit_Failed_Rollback_Only extends Connection_Exception
{
    public static function new(): self
    {
        return new self('Transaction commit failed because the transaction has been marked for rollback only.');
    }
}