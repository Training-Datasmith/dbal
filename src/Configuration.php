<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Schema_Manager_Factory;
use Psr\Cache\Cache_Item_Pool_Interface;
/**
 * Configuration container for the Doctrine DBAL.
 */
class Configuration
{
    /** @var Middleware[] */
    private array $middlewares = [];
    /**
     * The cache driver implementation that is used for query result caching.
     */
    private ?Cache_Item_Pool_Interface $result_cache = null;
    /**
     * The callable to use to filter schema assets.
     *
     * @var callable
     */
    protected $schema_assets_filter;
    /**
     * The default auto-commit mode for connections.
     */
    protected bool $auto_commit = true;
    private ?Schema_Manager_Factory $schema_manager_factory = null;
    public function __construct()
    {
        $this->schema_assets_filter = static fn(): bool => true;
    }
    /**
     * Gets the cache driver implementation that is used for query result caching.
     */
    public function get_result_cache(): ?Cache_Item_Pool_Interface
    {
        return $this->result_cache;
    }
    /**
     * Sets the cache driver implementation that is used for query result caching.
     */
    public function set_result_cache(Cache_Item_Pool_Interface $cache): void
    {
        $this->result_cache = $cache;
    }
    /**
     * Sets the callable to use to filter schema assets.
     */
    public function set_schema_assets_filter(callable $schema_assets_filter): void
    {
        $this->schema_assets_filter = $schema_assets_filter;
    }
    /**
     * Returns the callable to use to filter schema assets.
     */
    public function get_schema_assets_filter(): callable
    {
        return $this->schema_assets_filter;
    }
    /**
     * Sets the default auto-commit mode for connections.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * @see getAutoCommit
     *
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it
     */
    public function set_auto_commit(bool $auto_commit): void
    {
        $this->auto_commit = $auto_commit;
    }
    /**
     * Returns the default auto-commit mode for connections.
     *
     * @see    setAutoCommit
     *
     * @return bool True if auto-commit mode is enabled by default for connections, false otherwise.
     */
    public function get_auto_commit(): bool
    {
        return $this->auto_commit;
    }
    /**
     * @param Middleware[] $middlewares
     *
     * @return $this
     */
    public function set_middlewares(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }
    /** @return Middleware[] */
    public function get_middlewares(): array
    {
        return $this->middlewares;
    }
    public function get_schema_manager_factory(): ?Schema_Manager_Factory
    {
        return $this->schema_manager_factory;
    }
    /** @return $this */
    public function set_schema_manager_factory(Schema_Manager_Factory $schema_manager_factory): self
    {
        $this->schema_manager_factory = $schema_manager_factory;
        return $this;
    }
    public function get_disable_type_comments(): bool
    {
        return true;
    }
    /** @return $this */
    public function set_disable_type_comments(bool $disable_type_comments): self
    {
        if (!$disable_type_comments) {
            throw new InvalidArgumentException('Column comments cannot be enabled anymore.');
        }
        return $this;
    }
}