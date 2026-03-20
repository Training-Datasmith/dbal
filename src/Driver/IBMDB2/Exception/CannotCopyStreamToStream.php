<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Cannot_Copy_Stream_To_Stream extends Abstract_Exception
{
    /** @phpstan-param array{message: string, ...}|null $error */
    public static function new(?array $error): self
    {
        $message = 'Could not copy source stream to temporary file';
        if ($error !== null) {
            $message .= ': ' . $error['message'];
        }
        return new self($message);
    }
}