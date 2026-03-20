<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sq_Lite3;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Exception extends Abstract_Exception
{
    public static function new(\Exception $exception): self
    {
        return new self($exception->get_message(), null, (int) $exception->get_code(), $exception);
    }
}