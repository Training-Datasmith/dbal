<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Host_Required extends Abstract_Exception
{
    public static function for_persistent_connection(): self
    {
        return new self('The "host" parameter is required for a persistent connection');
    }
}