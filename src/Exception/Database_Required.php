<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use function sprintf;
class Database_Required extends \Exception implements Exception
{
    public static function new(string $method_name): self
    {
        return new self(sprintf('A database is required for the method: %s.', $method_name));
    }
}