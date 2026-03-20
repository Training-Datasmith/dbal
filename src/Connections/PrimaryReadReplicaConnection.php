<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Connections;

use function array_rand;
use function assert;
use function count;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Statement;
use InvalidArgumentException;
use Sensitive_Parameter;
/**
 * Primary-Replica Connection
 *
 * Connection can be used with primary-replica setups.
 *
 * Important for the understanding of this connection should be how and when
 * it picks the replica or primary.
 *
 * 1. Replica if primary was never picked before and ONLY if 'getWrappedConnection'
 *    or 'executeQuery' is used.
 * 2. Primary picked when 'executeStatement', 'insert', 'delete', 'update', 'createSavepoint',
 *    'releaseSavepoint', 'beginTransaction', 'rollback', 'commit' or 'prepare' is called.
 * 3. If Primary was picked once during the lifetime of the connection it will always get picked afterwards.
 * 4. One replica connection is randomly picked ONCE during a request.
 *
 * ATTENTION: You can write to the replica with this connection if you execute a write query without
 * opening up a transaction. For example:
 *
 *      $conn = DriverManager::getConnection(...);
 *      $conn->executeQuery("DELETE FROM table");
 *
 * Be aware that Connection#executeQuery is a method specifically for READ
 * operations only.
 *
 * Use Connection#executeStatement for any SQL statement that changes/updates
 * state in the database (UPDATE, INSERT, DELETE or DDL statements).
 *
 * This connection is limited to replica operations using the
 * Connection#executeQuery operation only, because it wouldn't be compatible
 * with the ORM or SchemaManager code otherwise. Both use all the other
 * operations in a context where writes could happen to a replica, which makes
 * this restricted approach necessary.
 *
 * You can manually connect to the primary at any time by calling:
 *
 *      $conn->ensureConnectedToPrimary();
 *
 * Instantiation through the DriverManager looks like:
 *
 * @phpstan-import-type Params from DriverManager
 * @phpstan-import-type OverrideParams from DriverManager
 * @example
 *
 * $conn = DriverManager::getConnection(array(
 *    'wrapperClass' => 'Doctrine\DBAL\Connections\PrimaryReadReplicaConnection',
 *    'driver' => 'pdo_mysql',
 *    'primary' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
 *    'replica' => array(
 *        array('user' => 'replica1', 'password' => '', 'host' => '', 'dbname' => ''),
 *        array('user' => 'replica2', 'password' => '', 'host' => '', 'dbname' => ''),
 *    )
 * ));
 *
 * You can also pass 'driverOptions' and any other documented option to each of this drivers
 * to pass additional information.
 */
class Primary_Read_Replica_Connection extends Connection
{
    /**
     * Primary and Replica connection (one of the randomly picked replicas).
     *
     * @var array<string, DriverConnection|null>
     */
    protected array $connections = ['primary' => null, 'replica' => null];
    /**
     * You can keep the replica connection and then switch back to it
     * during the request if you know what you are doing.
     */
    protected bool $keep_replica = false;
    /**
     * Creates Primary Replica Connection.
     *
     * @internal The connection can be only instantiated by the driver manager.
     *
     * @param array<string, mixed> $params
     * @phpstan-param Params $params
     */
    public function __construct(array $params, Driver $driver, ?Configuration $config = null)
    {
        if (!isset($params['replica'], $params['primary'])) {
            throw new InvalidArgumentException('primary or replica configuration missing');
        }
        if (count($params['replica']) === 0) {
            throw new InvalidArgumentException('You have to configure at least one replica.');
        }
        if (isset($params['driver'])) {
            $params['primary']['driver'] = $params['driver'];
            foreach ($params['replica'] as $replica_key => $replica) {
                $params['replica'][$replica_key]['driver'] = $params['driver'];
            }
        }
        $this->keep_replica = !empty($params['keepReplica']);
        parent::__construct($params, $driver, $config);
    }
    /**
     * Checks if the connection is currently towards the primary or not.
     */
    public function is_connected_to_primary(): bool
    {
        return $this->_conn !== null && $this->_conn === $this->connections['primary'];
    }
    public function connect(?string $connection_name = null): Driver_Connection
    {
        if ($connection_name !== null) {
            throw new InvalidArgumentException('Passing a connection name as first argument is not supported anymore.' . ' Use ensureConnectedToPrimary()/ensureConnectedToReplica() instead.');
        }
        return $this->perform_connect();
    }
    /** @throws Exception */
    protected function perform_connect(?string $connection_name = null): Driver_Connection
    {
        $requested_connection_change = $connection_name !== null;
        $connection_name ??= 'replica';
        if ($connection_name !== 'replica' && $connection_name !== 'primary') {
            throw new InvalidArgumentException('Invalid option to connect(), only primary or replica allowed.');
        }
        // If we have a connection open, and this is not an explicit connection
        // change request, then abort right here, because we are already done.
        // This prevents writes to the replica in case of "keepReplica" option enabled.
        if ($this->_conn !== null && !$requested_connection_change) {
            return $this->_conn;
        }
        $force_primary_as_replica = false;
        if ($this->get_transaction_nesting_level() > 0) {
            $connection_name = 'primary';
            $force_primary_as_replica = true;
        }
        if (isset($this->connections[$connection_name])) {
            $this->_conn = $this->connections[$connection_name];
            if ($force_primary_as_replica && !$this->keep_replica) {
                $this->connections['replica'] = $this->_conn;
            }
            return $this->_conn;
        }
        if ($connection_name === 'primary') {
            $this->connections['primary'] = $this->_conn = $this->connect_to($connection_name);
            // Set replica connection to primary to avoid invalid reads
            if (!$this->keep_replica) {
                $this->connections['replica'] = $this->connections['primary'];
            }
        } else {
            $this->connections['replica'] = $this->_conn = $this->connect_to($connection_name);
        }
        return $this->_conn;
    }
    /**
     * Connects to the primary node of the database cluster.
     *
     * All following statements after this will be executed against the primary node.
     *
     * @throws Exception
     */
    public function ensure_connected_to_primary(): void
    {
        $this->perform_connect('primary');
    }
    /**
     * Connects to a replica node of the database cluster.
     *
     * All following statements after this will be executed against the replica node,
     * unless the keepReplica option is set to false and a primary connection
     * was already opened.
     *
     * @throws Exception
     */
    public function ensure_connected_to_replica(): void
    {
        $this->perform_connect('replica');
    }
    /**
     * Connects to a specific connection.
     *
     * @throws Exception
     */
    protected function connect_to(string $connection_name): Driver_Connection
    {
        $params = $this->get_params();
        assert(isset($params['primary']));
        if ($connection_name === 'primary') {
            $connection_params = $params['primary'];
        } else {
            assert(isset($params['replica']));
            $connection_params = $this->choose_replica_connection_parameters($params['primary'], $params['replica']);
        }
        try {
            return $this->driver->connect($connection_params);
        } catch (Driver_Exception $e) {
            throw $this->convert_exception($e);
        }
    }
    /**
     * @param OverrideParams        $primary
     * @param array<OverrideParams> $replicas
     *
     * @return array<string, mixed>
     * @phpstan-return OverrideParams
     */
    protected function choose_replica_connection_parameters(
        #[Sensitive_Parameter]
        array $primary,
        #[Sensitive_Parameter]
        array $replicas
    ): array
    {
        $params = $replicas[array_rand($replicas)];
        if (!isset($params['charset']) && isset($primary['charset'])) {
            $params['charset'] = $primary['charset'];
        }
        return $params;
    }
    /**
     * {@inheritDoc}
     */
    public function execute_statement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->ensure_connected_to_primary();
        return parent::execute_statement($sql, $params, $types);
    }
    public function begin_transaction(): void
    {
        $this->ensure_connected_to_primary();
        parent::begin_transaction();
    }
    public function commit(): void
    {
        $this->ensure_connected_to_primary();
        parent::commit();
    }
    public function roll_back(): void
    {
        $this->ensure_connected_to_primary();
        parent::roll_back();
    }
    public function close(): void
    {
        unset($this->connections['primary'], $this->connections['replica']);
        parent::close();
        $this->_conn = null;
        $this->connections = ['primary' => null, 'replica' => null];
    }
    public function create_savepoint(string $savepoint): void
    {
        $this->ensure_connected_to_primary();
        parent::create_savepoint($savepoint);
    }
    public function release_savepoint(string $savepoint): void
    {
        $this->ensure_connected_to_primary();
        parent::release_savepoint($savepoint);
    }
    public function rollback_savepoint(string $savepoint): void
    {
        $this->ensure_connected_to_primary();
        parent::rollback_savepoint($savepoint);
    }
    public function prepare(string $sql): Statement
    {
        $this->ensure_connected_to_primary();
        return parent::prepare($sql);
    }
}