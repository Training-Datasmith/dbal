<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Array_Parameters\Exception;

use Doctrine\DBAL\Array_Parameters\Exception;
use LogicException;
use function sprintf;
/** @internal */
class Missing_Positional_Parameter extends LogicException implements Exception
{
    public static function new(int $index): self
    {
        return new self(sprintf('Positional parameter at index %d does not have a bound value.', $index));
    }
}