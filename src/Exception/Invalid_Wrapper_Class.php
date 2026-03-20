<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Connection;
use function sprintf;
final class Invalid_Wrapper_Class extends InvalidArgumentException
{
    public static function new(string $wrapper_class): self
    {
        return new self(sprintf('The given wrapper class %s has to be a subtype of %s.', $wrapper_class, Connection::class));
    }
}