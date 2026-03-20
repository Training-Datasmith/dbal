<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

use function addcslashes;
use function assert;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Exception\Identity_Columns_Not_Supported;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\SQL\Parser;
use function is_resource;
use function oci_commit;
use function oci_parse;
use function oci_rollback;
use function oci_server_version;
use function preg_match;
use function str_replace;
final readonly class Connection implements Connection_Interface
{
    private Parser $parser;
    private Execution_Mode $execution_mode;
    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private mixed $connection)
    {
        $this->parser = new Parser(false);
        $this->execution_mode = new Execution_Mode();
    }
    public function get_server_version(): string
    {
        $version = oci_server_version($this->connection);
        assert($version !== false);
        $result = preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $version, $matches);
        assert($result === 1);
        return $matches[1];
    }
    /**
     * @throws Parser\Exception
     * @throws Error
     */
    public function prepare(string $sql): Statement
    {
        $visitor = new Convert_Positional_To_Named_Placeholders();
        $this->parser->parse($sql, $visitor);
        $statement = @oci_parse($this->connection, $visitor->get_sql());
        if (!is_resource($statement)) {
            throw Error::new($this->connection);
        }
        return new Statement($this->connection, $statement, $visitor->get_parameter_map(), $this->execution_mode);
    }
    /**
     * @throws Exception
     * @throws Parser\Exception
     */
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }
    public function quote(string $value): string
    {
        return "'" . addcslashes(str_replace("'", "''", $value), "\x00\n\r\\\x1a") . "'";
    }
    /**
     * @throws Exception
     * @throws Parser\Exception
     */
    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->row_count();
    }
    public function last_insert_id(): int|string
    {
        throw Identity_Columns_Not_Supported::new();
    }
    public function begin_transaction(): void
    {
        $this->execution_mode->disable_auto_commit();
    }
    public function commit(): void
    {
        if (!@oci_commit($this->connection)) {
            throw Error::new($this->connection);
        }
        $this->execution_mode->enable_auto_commit();
    }
    public function roll_back(): void
    {
        if (!oci_rollback($this->connection)) {
            throw Error::new($this->connection);
        }
        $this->execution_mode->enable_auto_commit();
    }
    /** @return resource */
    public function get_native_connection()
    {
        return $this->connection;
    }
}