<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception\Invalid_Column_Type;

use Doctrine\DBAL\Exception\Invalid_Column_Type;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use function get_debug_type;
use function sprintf;
final class Column_Values_Required extends Invalid_Column_Type
{
    /**
     * @param AbstractPlatform $platform The target platform
     * @param string           $type     The SQL column type
     */
    public static function new(Abstract_Platform $platform, string $type): self
    {
        return new self(sprintf('%s requires the values of a %s column to be specified', get_debug_type($platform), $type));
    }
}