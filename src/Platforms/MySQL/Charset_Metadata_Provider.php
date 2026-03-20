<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql;

/** @internal */
interface Charset_Metadata_Provider
{
    /** @return ?non-empty-string */
    public function get_default_charset_collation(string $charset): ?string;
}