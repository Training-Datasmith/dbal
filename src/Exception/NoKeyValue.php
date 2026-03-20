<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use function sprintf;
/** @internal */
final class No_Key_Value extends \Exception implements Exception
{
    public static function from_column_count(int $column_count): self
    {
        return new self(sprintf('Fetching as key-value pairs requires the result to contain at least 2 columns, %d given.', $column_count));
    }
}