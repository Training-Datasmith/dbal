<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function get_debug_type;
use function sprintf;
final class Invalid_Configuration extends Abstract_Exception
{
    public static function not_a_string_or_null(string $key, mixed $value): self
    {
        return new self(sprintf('The %s configuration parameter is expected to be either a string or null, got %s.', $key, get_debug_type($value)));
    }
}