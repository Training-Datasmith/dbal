<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function sprintf;
final class Unknown_Parameter extends Abstract_Exception
{
    public static function new(string $param): self
    {
        return new self(sprintf('Could not find parameter %s in the SQL statement', $param));
    }
}