<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql\Exception;

use Doctrine\DBAL\Driver\Exception;
use function sprintf;
use UnexpectedValueException;
final class Unexpected_Value extends UnexpectedValueException implements Exception
{
    public static function new(string $value, string $type): self
    {
        return new self(sprintf('Unexpected value "%s" of type "%s" returned by Postgres', $value, $type));
    }
    public function get_sql_state(): null
    {
        return null;
    }
}