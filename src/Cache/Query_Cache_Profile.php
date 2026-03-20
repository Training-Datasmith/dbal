<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Cache\Exception\No_Cache_Key;
use Doctrine\DBAL\Connection;
use function hash;
use Psr\Cache\Cache_Item_Pool_Interface;
use function serialize;
use function sha1;
/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 *
 * @phpstan-import-type WrapperParameterType from Connection
 * @final
 */
class Query_Cache_Profile
{
    public function __construct(private readonly int $lifetime = 0, private readonly ?string $cache_key = null, private readonly ?Cache_Item_Pool_Interface $result_cache = null)
    {
    }
    public function get_result_cache(): ?Cache_Item_Pool_Interface
    {
        return $this->result_cache;
    }
    public function get_lifetime(): int
    {
        return $this->lifetime;
    }
    /** @throws CacheException */
    public function get_cache_key(): string
    {
        if ($this->cache_key === null) {
            throw No_Cache_Key::new();
        }
        return $this->cache_key;
    }
    /**
     * Generates the real cache key from query, params, types and connection parameters.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @param array<string, mixed>             $connectionParams
     * @phpstan-param array<int, WrapperParameterType>|array<string, WrapperParameterType> $types
     *
     * @return array{string, string}
     */
    public function generate_cache_keys(string $sql, array $params, array $types, array $connection_params = []): array
    {
        if (isset($connection_params['password'])) {
            unset($connection_params['password']);
        }
        $real_cache_key = 'query=' . $sql . '&params=' . serialize($params) . '&types=' . serialize($types) . '&connectionParams=' . hash('sha256', serialize($connection_params));
        // should the key be automatically generated using the inputs or is the cache key set?
        $cache_key = $this->cache_key ?? sha1($real_cache_key);
        return [$cache_key, $real_cache_key];
    }
    public function set_result_cache(Cache_Item_Pool_Interface $cache): Query_Cache_Profile
    {
        return new Query_Cache_Profile($this->lifetime, $this->cache_key, $cache);
    }
    public function set_cache_key(?string $cache_key): self
    {
        return new Query_Cache_Profile($this->lifetime, $cache_key, $this->result_cache);
    }
    public function set_lifetime(int $lifetime): self
    {
        return new Query_Cache_Profile($lifetime, $this->cache_key, $this->result_cache);
    }
}