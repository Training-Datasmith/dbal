<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function sprintf;
/** @internal */
final class Unknown_Parameter_Index extends Abstract_Exception
{
    public static function new(int $index): self
    {
        return new self(sprintf('Could not find variable mapping with index %d, in the SQL statement', $index));
    }
}