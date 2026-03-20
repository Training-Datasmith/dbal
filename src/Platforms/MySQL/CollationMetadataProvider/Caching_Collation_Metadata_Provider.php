<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql\Collation_Metadata_Provider;

use function array_key_exists;
use Doctrine\DBAL\Platforms\My_Sql\Collation_Metadata_Provider;
/** @internal */
final class Caching_Collation_Metadata_Provider implements Collation_Metadata_Provider
{
    /** @var array<non-empty-string,?non-empty-string> */
    private array $cache = [];
    public function __construct(private readonly Collation_Metadata_Provider $collation_metadata_provider)
    {
    }
    public function get_collation_charset(string $collation): ?string
    {
        if (array_key_exists($collation, $this->cache)) {
            return $this->cache[$collation];
        }
        return $this->cache[$collation] = $this->collation_metadata_provider->get_collation_charset($collation);
    }
}