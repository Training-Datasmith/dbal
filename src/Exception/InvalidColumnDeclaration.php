<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;
use function sprintf;
final class Invalid_Column_Declaration extends LogicException implements Exception
{
    public static function from_invalid_column_type(string $column_name, Invalid_Column_Type $e): self
    {
        return new self(sprintf('Column "%s" has invalid type', $column_name), 0, $e);
    }
}