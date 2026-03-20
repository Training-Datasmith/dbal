<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql\Charset_Metadata_Provider;

use function array_key_exists;
use Doctrine\DBAL\Platforms\My_Sql\Charset_Metadata_Provider;
/** @internal */
final class Caching_Charset_Metadata_Provider implements Charset_Metadata_Provider
{
    /** @var array<string,?non-empty-string> */
    private array $cache = [];
    public function __construct(private readonly Charset_Metadata_Provider $charset_metadata_provider)
    {
    }
    public function get_default_charset_collation(string $charset): ?string
    {
        if (array_key_exists($charset, $this->cache)) {
            return $this->cache[$charset];
        }
        return $this->cache[$charset] = $this->charset_metadata_provider->get_default_charset_collation($charset);
    }
}