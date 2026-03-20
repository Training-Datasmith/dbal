<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Driver;
use function sprintf;
final class Invalid_Driver_Class extends InvalidArgumentException
{
    public static function new(string $driver_class): self
    {
        return new self(sprintf('The given driver class %s has to implement the %s interface.', $driver_class, Driver::class));
    }
}