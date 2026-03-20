<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8\Exception;

use function assert;
use Doctrine\DBAL\Driver\Abstract_Exception;
use function oci_error;
/** @internal */
final class Connection_Failed extends Abstract_Exception
{
    public static function new(): self
    {
        $error = oci_error();
        assert($error !== false);
        return new self($error['message'], null, $error['code']);
    }
}