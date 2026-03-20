<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Array_Parameters\Exception;

use Doctrine\DBAL\Array_Parameters\Exception;
use LogicException;
use function sprintf;
class Missing_Named_Parameter extends LogicException implements Exception
{
    public static function new(string $name): self
    {
        return new self(sprintf('Named parameter "%s" does not have a bound value.', $name));
    }
}