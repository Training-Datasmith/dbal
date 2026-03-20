<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Abstract_Exception;
use function sprintf;
/** @internal */
final class Non_Stream_Resource_Used_As_Large_Object extends Abstract_Exception
{
    public static function new(int $parameter): self
    {
        return new self(sprintf('The resource passed as a LARGE_OBJECT parameter #%d must be of type "stream"', $parameter));
    }
}