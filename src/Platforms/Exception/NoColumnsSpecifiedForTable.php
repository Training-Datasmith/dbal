<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Exception;

use LogicException;
use function sprintf;
final class No_Columns_Specified_For_Table extends LogicException implements Platform_Exception
{
    public static function new(string $table_name): self
    {
        return new self(sprintf('No columns specified for table "%s".', $table_name));
    }
}