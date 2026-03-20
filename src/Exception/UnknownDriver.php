<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use function implode;
use function sprintf;
final class Unknown_Driver extends InvalidArgumentException
{
    /** @param string[] $knownDrivers */
    public static function new(string $unknown_driver_name, array $known_drivers): self
    {
        return new self(sprintf('The given driver "%s" is unknown, Doctrine currently supports only the following drivers: %s', $unknown_driver_name, implode(', ', $known_drivers)));
    }
}