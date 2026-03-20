<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Exception;

use Exception;
use function sprintf;
final class Invalid_Platform_Version extends Exception implements Platform_Exception
{
    /**
     * Returns a new instance for an invalid specified platform version.
     *
     * @param string $version        The invalid platform version given.
     * @param string $expectedFormat The expected platform version format.
     */
    public static function new(string $version, string $expected_format): self
    {
        return new self(sprintf('Invalid platform version "%s" specified. The platform version has to be specified in the format: "%s".', $version, $expected_format));
    }
}