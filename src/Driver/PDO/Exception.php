<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Abstract_Exception;
use PDOException;
/** @internal */
final class Exception extends Abstract_Exception
{
    public static function new(PDOException $exception): self
    {
        if ($exception->error_info !== null) {
            [$sql_state, $code] = $exception->error_info;
            $code ??= 0;
        } else {
            $code = $exception->get_code();
            $sql_state = null;
        }
        return new self($exception->get_message(), $sql_state, $code, $exception);
    }
}