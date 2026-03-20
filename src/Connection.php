<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use function array_key_exists;
use function array_merge;
use Closure;
use function count;
use Doctrine\DBAL\Cache\Array_Result;
use Doctrine\DBAL\Cache\Cache_Exception;
use Doctrine\DBAL\Cache\Exception\No_Result_Driver_Configured;
use Doctrine\DBAL\Cache\Query_Cache_Profile;
use Doctrine\DBAL\Connection\Static_Server_Version_Provider;
use Doctrine\DBAL\Driver\API\Exception_Converter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception as TheDriverException;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception\Commit_Failed_Rollback_Only;
use Doctrine\DBAL\Exception\Connection_Lost;
use Doctrine\DBAL\Exception\Deadlock_Exception;
use Doctrine\DBAL\Exception\Driver_Exception;
use Doctrine\DBAL\Exception\Foreign_Key_Constraint_Violation_Exception;
use Doctrine\DBAL\Exception\No_Active_Transaction;
use Doctrine\DBAL\Exception\ParseError;
use Doctrine\DBAL\Exception\Savepoints_Not_Supported;
use Doctrine\DBAL\Exception\Transaction_Rolled_Back;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Query\Expression\Expression_Builder;
use Doctrine\DBAL\Query\Query_Builder;
use Doctrine\DBAL\Schema\Abstract_Schema_Manager;
use Doctrine\DBAL\Schema\Default_Schema_Manager_Factory;
use Doctrine\DBAL\Schema\Schema_Manager_Factory;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use function implode;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_string;
use function key;
use Sensitive_Parameter;
use function sprintf;
use Throwable;
use Traversable;
/**
 * A database abstraction-level connection that implements features like transaction isolation levels,
 * configuration, emulated transaction nesting, lazy connecting and more.
 *
 * @phpstan-import-type Params from DriverManager
 * @phpstan-type WrapperParameterType = string|Type|ParameterType|ArrayParameterType
 * @phpstan-type WrapperParameterTypeArray = array<
 *    int<0, max>,
 *    WrapperParameterType>|array<string, WrapperParameterType
 *  >
 * @phpstan-consistent-constructor
 */
class Connection implements Server_Version_Provider
{
    /**
     * The wrapped driver connection.
     */
    protected ?Driver_Connection $_conn = null;
    protected Configuration $_config;
    /**
     * The current auto-commit mode of this connection.
     */
    private bool $auto_commit = true;
    /**
     * The transaction nesting level.
     */
    private int $transaction_nesting_level = 0;
    /**
     * The currently active transaction isolation level or NULL before it has been determined.
     */
    private ?Transaction_Isolation_Level $transaction_isolation_level = null;
    /**
     * The database platform object used by the connection or NULL before it's initialized.
     */
    private ?Abstract_Platform $platform = null;
    private ?Exception_Converter $exception_converter = null;
    private ?Parser $parser = null;
    /**
     * Flag that indicates whether the current transaction is marked for rollback only.
     */
    private bool $is_rollback_only = false;
    private readonly Schema_Manager_Factory $schema_manager_factory;
    /**
     * Initializes a new instance of the Connection class.
     *
     * @internal The connection can be only instantiated by the driver manager.
     *
     * @param array<string, mixed> $params The connection parameters.
     * @param Driver               $driver The driver to use.
     * @param Configuration|null   $config The configuration, optional.
     * @phpstan-param Params $params
     */
    public function __construct(
        /**
         * The parameters used during creation of the Connection instance.
         *
         * @phpstan-var Params
         */
        #[Sensitive_Parameter]
        private array $params,
        protected Driver $driver,
        ?Configuration $config = null
    )
    {
        $this->_config = $config ?? new Configuration();
        $this->auto_commit = $this->_config->get_auto_commit();
        $this->schema_manager_factory = $this->_config->get_schema_manager_factory() ?? new Default_Schema_Manager_Factory();
    }
    /**
     * Gets the parameters used during instantiation.
     *
     * @internal
     *
     * @return array<string,mixed>
     * @phpstan-return Params
     */
    public function get_params(): array
    {
        return $this->params;
    }
    /**
     * Gets the name of the currently selected database.
     *
     * @return ?non-empty-string The name of the database or NULL if a database is not selected.
     *                           The platforms which don't support the concept of a database (e.g. embedded databases)
     *                           must always return a string as an indicator of an implicitly selected database.
     *
     * @throws Exception
     */
    public function get_database(): ?string
    {
        $platform = $this->get_database_platform();
        $query = $platform->get_dummy_select_sql($platform->get_current_database_expression());
        return $this->fetch_one($query);
    }
    /**
     * Gets the DBAL driver instance.
     */
    public function get_driver(): Driver
    {
        return $this->driver;
    }
    /**
     * Gets the Configuration used by the Connection.
     */
    public function get_configuration(): Configuration
    {
        return $this->_config;
    }
    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @throws Exception
     */
    public function get_database_platform(): Abstract_Platform
    {
        if ($this->platform === null) {
            $version_provider = $this;
            if (isset($this->params['serverVersion'])) {
                $version_provider = new Static_Server_Version_Provider($this->params['serverVersion']);
            } elseif (isset($this->params['primary']['serverVersion'])) {
                $version_provider = new Static_Server_Version_Provider($this->params['primary']['serverVersion']);
            }
            $this->platform = $this->driver->get_database_platform($version_provider);
        }
        return $this->platform;
    }
    /**
     * Creates an expression builder for the connection.
     */
    public function create_expression_builder(): Expression_Builder
    {
        return new Expression_Builder($this);
    }
    /**
     * Establishes the connection with the database and returns the underlying connection.
     *
     * @throws Exception
     */
    protected function connect(): Driver_Connection
    {
        if ($this->_conn !== null) {
            return $this->_conn;
        }
        try {
            $connection = $this->_conn = $this->driver->connect($this->params);
        } catch (Driver\Exception $e) {
            throw $this->convert_exception($e);
        }
        if ($this->auto_commit === false) {
            $this->begin_transaction();
        }
        return $connection;
    }
    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function get_server_version(): string
    {
        return $this->connect()->get_server_version();
    }
    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @see    setAutoCommit
     *
     * @return bool True if auto-commit mode is currently enabled for this connection, false otherwise.
     */
    public function is_auto_commit(): bool
    {
        return $this->auto_commit;
    }
    /**
     * Sets auto-commit mode for this connection.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * NOTE: If this method is called during a transaction and the auto-commit mode is changed, the transaction is
     * committed. If this method is called and the auto-commit mode is not changed, the call is a no-op.
     *
     * @see isAutoCommit
     *
     * @throws Exception
     */
    public function set_auto_commit(bool $auto_commit): void
    {
        // Mode not changed, no-op.
        if ($auto_commit === $this->auto_commit) {
            return;
        }
        $this->auto_commit = $auto_commit;
        // Commit all currently active transactions if any when switching auto-commit mode.
        if ($this->_conn === null || $this->transaction_nesting_level === 0) {
            return;
        }
        $this->commit_all();
    }
    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetch_associative(string $query, array $params = [], array $types = []): array|false
    {
        return $this->execute_query($query, $params, $types)->fetch_associative();
    }
    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return list<mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetch_numeric(string $query, array $params = [], array $types = []): array|false
    {
        return $this->execute_query($query, $params, $types)->fetch_numeric();
    }
    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetch_one(string $query, array $params = [], array $types = []): mixed
    {
        return $this->execute_query($query, $params, $types)->fetch_one();
    }
    /**
     * Whether an actual connection to the database is established.
     *
     * @phpstan-assert-if-true !null $this->_conn
     */
    public function is_connected(): bool
    {
        return $this->_conn !== null;
    }
    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function is_transaction_active(): bool
    {
        return $this->transaction_nesting_level > 0;
    }
    /**
     * Adds condition based on the criteria to the query components
     *
     * @param array<string, mixed> $criteria Map of key columns to their values
     *
     * @return array{list<string>, list<mixed>, list<string>}
     */
    private function get_criteria_condition(array $criteria): array
    {
        $columns = $values = $conditions = [];
        foreach ($criteria as $column_name => $value) {
            if ($value === null) {
                $conditions[] = $column_name . ' IS NULL';
                continue;
            }
            $columns[] = $column_name;
            $values[] = $value;
            $conditions[] = $column_name . ' = ?';
        }
        return [$columns, $values, $conditions];
    }
    /**
     * Executes an SQL DELETE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param array<string, mixed>                                                                  $criteria
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @return int|numeric-string The number of affected rows.
     *
     * @throws Exception
     */
    public function delete(string $table, array $criteria = [], array $types = []): int|string
    {
        [$columns, $values, $conditions] = $this->get_criteria_condition($criteria);
        $sql = 'DELETE FROM ' . $table;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        return $this->execute_statement($sql, $values, is_string(key($types)) ? $this->extract_type_values($columns, $types) : $types);
    }
    /**
     * Closes the connection.
     */
    public function close(): void
    {
        $this->_conn = null;
        $this->transaction_nesting_level = 0;
    }
    /**
     * Sets the transaction isolation level.
     *
     * @param TransactionIsolationLevel $level The level to set.
     *
     * @throws Exception
     */
    public function set_transaction_isolation(Transaction_Isolation_Level $level): void
    {
        $this->transaction_isolation_level = $level;
        $this->execute_statement($this->get_database_platform()->get_set_transaction_isolation_sql($level));
    }
    /**
     * Gets the currently active transaction isolation level.
     *
     * @return TransactionIsolationLevel The current transaction isolation level.
     *
     * @throws Exception
     */
    public function get_transaction_isolation(): Transaction_Isolation_Level
    {
        return $this->transaction_isolation_level ??= $this->get_database_platform()->get_default_transaction_isolation_level();
    }
    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param array<string, mixed>                                                                  $data
     * @param array<string, mixed>                                                                  $criteria
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @return int|numeric-string The number of affected rows.
     *
     * @throws Exception
     */
    public function update(string $table, array $data, array $criteria = [], array $types = []): int|string
    {
        $columns = $values = $conditions = $set = [];
        foreach ($data as $column_name => $value) {
            $columns[] = $column_name;
            $values[] = $value;
            $set[] = $column_name . ' = ?';
        }
        [$criteria_columns, $criteria_values, $criteria_conditions] = $this->get_criteria_condition($criteria);
        $columns = array_merge($columns, $criteria_columns);
        $values = array_merge($values, $criteria_values);
        $conditions = array_merge($conditions, $criteria_conditions);
        if (is_string(key($types))) {
            $types = $this->extract_type_values($columns, $types);
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set);
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        return $this->execute_statement($sql, $values, $types);
    }
    /**
     * Inserts a table row with specified data.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param array<string, mixed>                                                                  $data
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @return int|numeric-string The number of affected rows.
     *
     * @throws Exception
     */
    public function insert(string $table, array $data, array $types = []): int|string
    {
        if (count($data) === 0) {
            return $this->execute_statement('INSERT INTO ' . $table . ' () VALUES ()');
        }
        $columns = [];
        $values = [];
        $set = [];
        foreach ($data as $column_name => $value) {
            $columns[] = $column_name;
            $values[] = $value;
            $set[] = '?';
        }
        return $this->execute_statement('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')' . ' VALUES (' . implode(', ', $set) . ')', $values, is_string(key($types)) ? $this->extract_type_values($columns, $types) : $types);
    }
    /**
     * Extract ordered type list from an ordered column list and type map.
     *
     * @param array<int, string>                                                             $columns
     * @param array<int, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @return array<int<0, max>, string|ParameterType|Type>
     */
    private function extract_type_values(array $columns, array $types): array
    {
        $type_values = [];
        foreach ($columns as $column_name) {
            $type_values[] = $types[$column_name] ?? Parameter_Type::STRING;
        }
        return $type_values;
    }
    /**
     * Quotes a string so it can be safely used as a table or column name, even if
     * it is a reserved name.
     *
     * Delimiting style depends on the underlying database platform that is being used.
     *
     * NOTE: Just because you CAN use quoted identifiers does not mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @deprecated Use {@link quoteSingleIdentifier()} individually for each part of a qualified name instead.
     *
     * @param string $identifier The identifier to be quoted.
     *
     * @return string The quoted identifier.
     *
     * @throws Exception
     */
    public function quote_identifier(string $identifier): string
    {
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6590', <<<'DEPRECATION'
        Method %s is deprecated and will be removed in 5.0.
        Use quoteSingleIdentifier() individually for each part of a qualified name instead.
        DEPRECATION, __METHOD__);
        return $this->get_database_platform()->quote_identifier($identifier);
    }
    /**
     * Quotes a string so that it can be safely used as an identifier in SQL.
     *
     * @throws Exception
     */
    public function quote_single_identifier(string $identifier): string
    {
        return $this->get_database_platform()->quote_single_identifier($identifier);
    }
    /**
     * The usage of this method is discouraged. Use prepared statements
     * or {@see AbstractPlatform::quoteStringLiteral()} instead.
     *
     * @throws Exception
     */
    public function quote(string $value): string
    {
        return $this->connect()->quote($value);
    }
    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return list<list<mixed>>
     *
     * @throws Exception
     */
    public function fetch_all_numeric(string $query, array $params = [], array $types = []): array
    {
        return $this->execute_query($query, $params, $types)->fetch_all_numeric();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetch_all_associative(string $query, array $params = [], array $types = []): array
    {
        return $this->execute_query($query, $params, $types)->fetch_all_associative();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetch_all_key_value(string $query, array $params = [], array $types = []): array
    {
        return $this->execute_query($query, $params, $types)->fetch_all_key_value();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetch_all_associative_indexed(string $query, array $params = [], array $types = []): array
    {
        return $this->execute_query($query, $params, $types)->fetch_all_associative_indexed();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return list<mixed>
     *
     * @throws Exception
     */
    public function fetch_first_column(string $query, array $params = [], array $types = []): array
    {
        return $this->execute_query($query, $params, $types)->fetch_first_column();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return Traversable<int,list<mixed>>
     *
     * @throws Exception
     */
    public function iterate_numeric(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->execute_query($query, $params, $types)->iterate_numeric();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterate_associative(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->execute_query($query, $params, $types)->iterate_associative();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws Exception
     */
    public function iterate_key_value(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->execute_query($query, $params, $types)->iterate_key_value();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterate_associative_indexed(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->execute_query($query, $params, $types)->iterate_associative_indexed();
    }
    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     */
    public function iterate_column(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->execute_query($query, $params, $types)->iterate_column();
    }
    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement to prepare.
     *
     * @throws Exception
     */
    public function prepare(string $sql): Statement
    {
        $connection = $this->connect();
        try {
            $statement = $connection->prepare($sql);
        } catch (Driver\Exception $e) {
            throw $this->convert_exception_during_query($e, $sql);
        }
        return new Statement($this, $statement, $sql);
    }
    /**
     * Executes an, optionally parameterized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @throws Exception
     */
    public function execute_query(string $sql, array $params = [], array $types = [], ?Query_Cache_Profile $qcp = null): Result
    {
        if ($qcp !== null) {
            return $this->execute_cache_query($sql, $params, $types, $qcp);
        }
        $connection = $this->connect();
        try {
            if (count($params) > 0) {
                [$sql, $params, $types] = $this->expand_array_parameters($sql, $params, $types);
                $stmt = $connection->prepare($sql);
                $this->bind_parameters($stmt, $params, $types);
                $result = $stmt->execute();
            } else {
                $result = $connection->query($sql);
            }
            return new Result($result, $this);
        } catch (Driver\Exception $e) {
            throw $this->convert_exception_during_query($e, $sql, $params, $types);
        }
    }
    /**
     * Executes a caching query.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @throws CacheException
     * @throws Exception
     */
    public function execute_cache_query(string $sql, array $params, array $types, Query_Cache_Profile $qcp): Result
    {
        $result_cache = $qcp->get_result_cache() ?? $this->_config->get_result_cache();
        if ($result_cache === null) {
            throw No_Result_Driver_Configured::new();
        }
        $connection_params = $this->params;
        unset($connection_params['password']);
        [$cache_key, $real_key] = $qcp->generate_cache_keys($sql, $params, $types, $connection_params);
        // @phpstan-ignore missingType.checkedException
        $item = $result_cache->get_item($cache_key);
        if ($item->is_hit()) {
            $value = $item->get();
            if (!is_array($value)) {
                $value = [];
            }
            if (isset($value[$real_key]) && $value[$real_key] instanceof Array_Result) {
                return new Result(clone $value[$real_key], $this);
            }
        } else {
            $value = [];
        }
        $result = $this->execute_query($sql, $params, $types);
        $column_names = [];
        for ($i = 0; $i < $result->column_count(); $i++) {
            $column_names[] = $result->get_column_name($i);
        }
        $rows = $result->fetch_all_numeric();
        $value[$real_key] = new Array_Result($column_names, $rows);
        $item->set($value);
        $lifetime = $qcp->get_lifetime();
        if ($lifetime > 0) {
            $item->expires_after($lifetime);
        }
        $result_cache->save($item);
        return new Result(clone $value[$real_key], $this);
    }
    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return int|numeric-string
     *
     * @throws Exception
     */
    public function execute_statement(string $sql, array $params = [], array $types = []): int|string
    {
        $connection = $this->connect();
        try {
            if (count($params) > 0) {
                [$sql, $params, $types] = $this->expand_array_parameters($sql, $params, $types);
                $stmt = $connection->prepare($sql);
                $this->bind_parameters($stmt, $params, $types);
                return $stmt->execute()->row_count();
            }
            return $connection->exec($sql);
        } catch (Driver\Exception $e) {
            throw $this->convert_exception_during_query($e, $sql, $params, $types);
        }
    }
    /**
     * Returns the current transaction nesting level.
     *
     * @return int The nesting level. A value of 0 means there's no active transaction.
     */
    public function get_transaction_nesting_level(): int
    {
        return $this->transaction_nesting_level;
    }
    /**
     * Returns the ID of the last inserted row.
     *
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @throws Exception
     */
    public function last_insert_id(): int|string
    {
        try {
            return $this->connect()->last_insert_id();
        } catch (Driver\Exception $e) {
            throw $this->convert_exception($e);
        }
    }
    /**
     * Executes a function in a transaction.
     *
     * The function gets passed this Connection instance as an (optional) parameter.
     *
     * If an exception occurs during execution of the function or transaction commit,
     * the transaction is rolled back and the exception re-thrown.
     *
     * @param Closure(self):T $func The function to execute transactionally.
     *
     * @return T The value returned by $func
     *
     * @throws Throwable
     *
     * @template T
     */
    public function transactional(Closure $func): mixed
    {
        $this->begin_transaction();
        $successful = false;
        try {
            $res = $func($this);
            $successful = true;
        } catch (Connection_Lost $connection_lost) {
            // Catching here only to be able to prevent a rollback attempt
            throw $connection_lost;
        } finally {
            if (!isset($connection_lost) && !$successful) {
                $this->roll_back();
            }
        }
        $should_rollback = true;
        try {
            $this->commit();
            $should_rollback = false;
        } catch (The_Driver_Exception $t) {
            $should_rollback = !($t instanceof Transaction_Rolled_Back || $t instanceof Unique_Constraint_Violation_Exception || $t instanceof Foreign_Key_Constraint_Violation_Exception || $t instanceof Deadlock_Exception || $t instanceof Connection_Lost);
            throw $t;
        } finally {
            if ($should_rollback) {
                $this->roll_back();
            }
        }
        return $res;
    }
    /**
     * Sets if nested transactions should use savepoints.
     *
     * @deprecated No replacement planned
     *
     * @throws Exception
     */
    public function set_nest_transactions_with_savepoints(bool $nest_transactions_with_savepoints): void
    {
        if (!$nest_transactions_with_savepoints) {
            throw new InvalidArgumentException(sprintf('Calling %s with false to enable nesting transactions without savepoints is no longer supported.', __METHOD__));
        }
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/5383', '%s is deprecated and will be removed in 5.0', __METHOD__);
    }
    /**
     * Gets if nested transactions should use savepoints.
     *
     * @deprecated No replacement planned
     */
    public function get_nest_transactions_with_savepoints(): bool
    {
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/5383', '%s is deprecated and will be removed in 5.0', __METHOD__);
        return true;
    }
    /**
     * Returns the savepoint name to use for nested transactions.
     */
    protected function _get_nested_transaction_save_point_name(): string
    {
        return 'DOCTRINE_' . $this->transaction_nesting_level;
    }
    /** @throws Exception */
    public function begin_transaction(): void
    {
        $connection = $this->connect();
        ++$this->transaction_nesting_level;
        if ($this->transaction_nesting_level === 1) {
            try {
                $connection->begin_transaction();
            } catch (Driver\Exception $e) {
                throw $this->convert_exception($e);
            }
        } else {
            $this->create_savepoint($this->_get_nested_transaction_save_point_name());
        }
    }
    /** @throws Exception */
    public function commit(): void
    {
        if ($this->transaction_nesting_level === 0) {
            throw No_Active_Transaction::new();
        }
        if ($this->is_rollback_only) {
            throw Commit_Failed_Rollback_Only::new();
        }
        $connection = $this->connect();
        try {
            if ($this->transaction_nesting_level === 1) {
                try {
                    $connection->commit();
                } catch (Driver\Exception $e) {
                    throw $this->convert_exception($e);
                }
            } else {
                $this->release_savepoint($this->_get_nested_transaction_save_point_name());
            }
        } finally {
            $this->update_transaction_state_after_commit();
        }
    }
    /** @throws Exception */
    private function update_transaction_state_after_commit(): void
    {
        if ($this->transaction_nesting_level !== 0) {
            --$this->transaction_nesting_level;
        }
        if ($this->auto_commit !== false || $this->transaction_nesting_level !== 0) {
            return;
        }
        $this->begin_transaction();
    }
    /**
     * Commits all current nesting transactions.
     *
     * @throws Exception
     */
    private function commit_all(): void
    {
        while ($this->transaction_nesting_level !== 0) {
            if ($this->auto_commit === false && $this->transaction_nesting_level === 1) {
                // When in no auto-commit mode, the last nesting commit immediately starts a new transaction.
                // Therefore we need to do the final commit here and then leave to avoid an infinite loop.
                $this->commit();
                return;
            }
            $this->commit();
        }
    }
    /** @throws Exception */
    public function roll_back(): void
    {
        if ($this->transaction_nesting_level === 0) {
            throw No_Active_Transaction::new();
        }
        $connection = $this->connect();
        if ($this->transaction_nesting_level === 1) {
            $this->transaction_nesting_level = 0;
            try {
                $connection->roll_back();
            } catch (Driver\Exception $e) {
                throw $this->convert_exception($e);
            } finally {
                $this->is_rollback_only = false;
                if ($this->auto_commit === false) {
                    $this->begin_transaction();
                }
            }
        } else {
            $this->rollback_savepoint($this->_get_nested_transaction_save_point_name());
            --$this->transaction_nesting_level;
        }
    }
    /**
     * Creates a new savepoint.
     *
     * @param string $savepoint The name of the savepoint to create.
     *
     * @throws Exception
     */
    public function create_savepoint(string $savepoint): void
    {
        $platform = $this->get_database_platform();
        if (!$platform->supports_savepoints()) {
            throw Savepoints_Not_Supported::new();
        }
        $this->execute_statement($platform->create_save_point($savepoint));
    }
    /**
     * Releases the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to release.
     *
     * @throws Exception
     */
    public function release_savepoint(string $savepoint): void
    {
        $platform = $this->get_database_platform();
        if (!$platform->supports_savepoints()) {
            throw Savepoints_Not_Supported::new();
        }
        if (!$platform->supports_release_savepoints()) {
            return;
        }
        $this->execute_statement($platform->release_save_point($savepoint));
    }
    /**
     * Rolls back to the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to rollback to.
     *
     * @throws Exception
     */
    public function rollback_savepoint(string $savepoint): void
    {
        $platform = $this->get_database_platform();
        if (!$platform->supports_savepoints()) {
            throw Savepoints_Not_Supported::new();
        }
        $this->execute_statement($platform->rollback_save_point($savepoint));
    }
    /**
     * Provides access to the native database connection.
     *
     * @return resource|object
     *
     * @throws Exception
     */
    public function get_native_connection()
    {
        return $this->connect()->get_native_connection();
    }
    /**
     * Creates a SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     *
     * @throws Exception
     */
    public function create_schema_manager(): Abstract_Schema_Manager
    {
        return $this->schema_manager_factory->create_schema_manager($this);
    }
    /**
     * Marks the current transaction so that the only possible
     * outcome for the transaction to be rolled back.
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function set_rollback_only(): void
    {
        if ($this->transaction_nesting_level === 0) {
            throw No_Active_Transaction::new();
        }
        $this->is_rollback_only = true;
    }
    /**
     * Checks whether the current transaction is marked for rollback only.
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function is_rollback_only(): bool
    {
        if ($this->transaction_nesting_level === 0) {
            throw No_Active_Transaction::new();
        }
        return $this->is_rollback_only;
    }
    /**
     * Converts a given value to its database representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed  $value The value to convert.
     * @param string $type  The name of the DBAL mapping type.
     *
     * @return mixed The converted value.
     *
     * @throws Exception
     */
    public function convert_to_database_value(mixed $value, string $type): mixed
    {
        return Type::get_type($type)->convert_to_database_value($value, $this->get_database_platform());
    }
    /**
     * Converts a given value to its PHP representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed  $value The value to convert.
     * @param string $type  The name of the DBAL mapping type.
     *
     * @return mixed The converted type.
     *
     * @throws Exception
     */
    public function convert_to_php_value(mixed $value, string $type): mixed
    {
        return Type::get_type($type)->convert_to_php_value($value, $this->get_database_platform());
    }
    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type
     * or DBAL mapping type, to a given statement.
     *
     * @param list<mixed>|array<string, mixed>                                               $params
     * @param array<int, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @throws Exception
     */
    private function bind_parameters(Driver_Statement $stmt, array $params, array $types): void
    {
        // Check whether parameters are positional or named. Mixing is not allowed.
        if (is_int(key($params))) {
            $bind_index = 1;
            foreach ($params as $key => $value) {
                if (array_key_exists($key, $types)) {
                    $type = $types[$key];
                    [$value, $binding_type] = $this->get_binding_info($value, $type);
                } else {
                    $binding_type = Parameter_Type::STRING;
                }
                try {
                    $stmt->bind_value($bind_index, $value, $binding_type);
                } catch (Driver\Exception $e) {
                    throw $this->convert_exception($e);
                }
                ++$bind_index;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (array_key_exists($name, $types)) {
                    $type = $types[$name];
                    [$value, $binding_type] = $this->get_binding_info($value, $type);
                } else {
                    $binding_type = Parameter_Type::STRING;
                }
                try {
                    $stmt->bind_value($name, $value, $binding_type);
                } catch (Driver\Exception $e) {
                    throw $this->convert_exception($e);
                }
            }
        }
    }
    /**
     * Gets the binding type of a given type.
     *
     * @param mixed                     $value The value to bind.
     * @param string|ParameterType|Type $type  The type to bind.
     *
     * @return array{mixed, ParameterType} [0] => the (escaped) value, [1] => the binding type.
     *
     * @throws Exception
     */
    private function get_binding_info(mixed $value, string|Parameter_Type|Type $type): array
    {
        if (is_string($type)) {
            $type = Type::get_type($type);
        }
        if ($type instanceof Type) {
            $value = $type->convert_to_database_value($value, $this->get_database_platform());
            $binding_type = $type->get_binding_type();
        } else {
            $binding_type = $type;
        }
        return [$value, $binding_type];
    }
    /**
     * Creates a new instance of a SQL query builder.
     */
    public function create_query_builder(): Query_Builder
    {
        return new Query\Query_Builder($this);
    }
    /**
     * @internal
     *
     * @param list<mixed>|array<string,mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     */
    final public function convert_exception_during_query(Driver\Exception $e, string $sql, array $params = [], array $types = []): Driver_Exception
    {
        return $this->handle_driver_exception($e, new Query($sql, $params, $types));
    }
    /** @internal */
    final public function convert_exception(Driver\Exception $e): Driver_Exception
    {
        return $this->handle_driver_exception($e, null);
    }
    /**
     * @param list<mixed>|array<string, mixed> $params
     * @phpstan-param WrapperParameterTypeArray $types
     *
     * @return array{
     *     string,
     *     list<mixed>|array<string, mixed>,
     *     array<int<0, max>, string|ParameterType|Type>|array<string, string|ParameterType|Type>
     * }
     *
     * @throws Exception
     */
    private function expand_array_parameters(string $sql, array $params, array $types): array
    {
        $needs_conversion = false;
        $non_array_types = [];
        if (is_string(key($params))) {
            $needs_conversion = true;
        } else {
            foreach ($types as $key => $type) {
                if ($type instanceof Array_Parameter_Type) {
                    $needs_conversion = true;
                    break;
                }
                $non_array_types[$key] = $type;
            }
        }
        if (!$needs_conversion) {
            return [$sql, $params, $non_array_types];
        }
        $this->parser ??= $this->get_database_platform()->create_sql_parser();
        $visitor = new Expand_Array_Parameters($params, $types);
        try {
            $this->parser->parse($sql, $visitor);
        } catch (Parser\Exception $e) {
            throw ParseError::from_parser_exception($e);
        }
        return [$visitor->get_sql(), $visitor->get_parameters(), $visitor->get_types()];
    }
    private function handle_driver_exception(Driver\Exception $driver_exception, ?Query $query): Driver_Exception
    {
        $this->exception_converter ??= $this->driver->get_exception_converter();
        $exception = $this->exception_converter->convert($driver_exception, $query);
        if ($exception instanceof Connection_Lost) {
            $this->close();
        }
        return $exception;
    }
}