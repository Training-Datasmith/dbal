<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Schema_Manager_Factory;
use Psr\Cache\Cache_Item_Pool_Interface;
/**
 * Configuration container for the Doctrine DBAL.
 *
 * Holds settings that apply to all connections created from a given set of parameters.
 * Instances are typically built once at application boot and shared across connections.
 *
 * @since 2.0
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
     * Gets the PSR-6 cache pool used for query result caching.
     *
     * When set, DBAL stores query results keyed by SQL + parameters so that
     * identical queries can be served from cache without hitting the database.
     *
     * @return Cache_Item_Pool_Interface|null The configured result cache pool, or null if caching is disabled.
     *
     * @see set_result_cache()
     * @since 2.0
     */
    public function get_result_cache(): ?Cache_Item_Pool_Interface
    {
        return $this->result_cache;
    }

    /**
     * Sets the PSR-6 cache pool to use for query result caching.
     *
     * Once set, any query executed with a {@see Query_Cache_Profile} will store its
     * result in this pool. Passing a shared pool (e.g. Redis/APCu) enables
     * cross-request caching.
     *
     * @param Cache_Item_Pool_Interface $cache The PSR-6 cache pool to use.
     *
     * @since 2.0
     */
    public function set_result_cache(Cache_Item_Pool_Interface $cache): void
    {
        $this->result_cache = $cache;
    }

    /**
     * Sets a callable that decides which schema assets (tables, sequences) are managed.
     *
     * The callable receives the asset name as a string and must return true to include
     * the asset or false to exclude it. Useful for multi-tenant schemas where only a
     * subset of tables belongs to the application.
     *
     * @param callable $schema_assets_filter A callable(string): bool that filters asset names.
     *
     * @since 2.2
     */
    public function set_schema_assets_filter(callable $schema_assets_filter): void
    {
        $this->schema_assets_filter = $schema_assets_filter;
    }

    /**
     * Returns the callable used to filter schema assets.
     *
     * The default filter accepts all assets (always returns true).
     *
     * @return callable A callable(string): bool.
     *
     * @see set_schema_assets_filter()
     * @since 2.2
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
    /**
     * Returns the schema manager factory, if one has been configured.
     *
     * When null, the connection resolves the factory from the default
     * {@see Default_Schema_Manager_Factory}.
     *
     * @return Schema_Manager_Factory|null The configured factory, or null for the default.
     *
     * @since 3.5
     */
    public function get_schema_manager_factory(): ?Schema_Manager_Factory
    {
        return $this->schema_manager_factory;
    }

    /**
     * Overrides the schema manager factory used by connections built from this configuration.
     *
     * Use this to inject a custom factory that returns a decorated or specialised
     * schema manager (e.g. one that hides system tables).
     *
     * @param Schema_Manager_Factory $schema_manager_factory The factory to use.
     *
     * @return $this
     *
     * @since 3.5
     */
    public function set_schema_manager_factory(Schema_Manager_Factory $schema_manager_factory): self
    {
        $this->schema_manager_factory = $schema_manager_factory;
        return $this;
    }

    /**
     * Returns whether type-hint comments in generated DDL are disabled.
     *
     * As of DBAL 4.0, type comments are permanently disabled; this method
     * always returns true.
     *
     * @return bool Always true.
     *
     * @deprecated Type comments have been removed in DBAL 4.0 and cannot be re-enabled.
     *
     * @since 4.0
     */
    public function get_disable_type_comments(): bool
    {
        return true;
    }

    /**
     * @deprecated Type comments have been permanently removed in DBAL 4.0.
     *             Calling this method with false will throw an exception.
     *
     * @param bool $disable_type_comments Must be true; passing false throws InvalidArgumentException.
     *
     * @return $this
     *
     * @throws InvalidArgumentException If $disable_type_comments is false.
     *
     * @since 4.0
     */
    public function set_disable_type_comments(bool $disable_type_comments): self
    {
        if (!$disable_type_comments) {
            throw new InvalidArgumentException('Column comments cannot be enabled anymore.');
        }
        return $this;
    }
}