<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
/** @internal */
final class Prepare_Failed extends Abstract_Exception
{
    /** @phpstan-param array{message: string, ...}|null $error */
    public static function new(?array $error): self
    {
        if ($error === null) {
            return new self('Unknown error');
        }
        return new self($error['message']);
    }
}