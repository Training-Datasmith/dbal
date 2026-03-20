<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception\Invalid_Column_Type;

use Doctrine\DBAL\Exception\Invalid_Column_Type;
/** @internal */
final class Column_Precision_Required extends Invalid_Column_Type
{
    public static function new(): self
    {
        return new self('Column precision is not specified');
    }
}