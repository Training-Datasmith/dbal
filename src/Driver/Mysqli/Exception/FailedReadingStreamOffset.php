<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function sprintf;
/** @internal */
final class Failed_Reading_Stream_Offset extends Abstract_Exception
{
    public static function new(int $parameter): self
    {
        return new self(sprintf('Failed reading the stream resource for parameter #%d.', $parameter));
    }
}