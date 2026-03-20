<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql;

/** @internal */
interface Collation_Metadata_Provider
{
    /**
     * @param non-empty-string $collation
     *
     * @return ?non-empty-string
     */
    public function get_collation_charset(string $collation): ?string;
}