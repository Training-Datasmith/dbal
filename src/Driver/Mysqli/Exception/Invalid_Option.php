<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function sprintf;
/** @internal */
final class Invalid_Option extends Abstract_Exception
{
    public static function from_option(int $option, mixed $value): self
    {
        return new self(sprintf('Failed to set option %d with value "%s"', $option, $value));
    }
}