<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Invalid_Configuration extends Abstract_Exception
{
    public static function for_persistent_and_exclusive(): self
    {
        return new self('The "persistent" parameter and the "exclusive" driver option are mutually exclusive');
    }
}