<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use Throwable;
/** @internal */
final class Identity_Columns_Not_Supported extends Abstract_Exception
{
    public static function new(?Throwable $previous = null): self
    {
        return new self('The driver does not support identity columns.', null, 0, $previous);
    }
}