<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use function assert;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\No_Identity_Value;
use Doctrine\DBAL\SQL\Parser;
use function pg_close;
use function pg_escape_literal;
use function pg_get_result;
use function pg_last_error;
use function pg_result_error;
use function pg_send_prepare;
use function pg_send_query;
use function pg_version;
use Pg_Sql\Connection as PgSqlConnection;
use function uniqid;
final readonly class Connection implements Connection_Interface
{
    private Parser $parser;
    public function __construct(private Pg_Sql_Connection $connection)
    {
        $this->parser = new Parser(false);
    }
    public function __destruct()
    {
        // @phpstan-ignore isset.initializedProperty
        if (!isset($this->connection)) {
            return;
        }
        @pg_close($this->connection);
    }
    public function prepare(string $sql): Statement
    {
        $visitor = new Convert_Parameters();
        /** @phpstan-ignore missingType.checkedException */
        $this->parser->parse($sql, $visitor);
        $statement_name = uniqid('dbal', true);
        if (@pg_send_prepare($this->connection, $statement_name, $visitor->get_sql()) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }
        $result = @pg_get_result($this->connection);
        assert($result !== false);
        if ((bool) pg_result_error($result)) {
            throw Exception::from_result($result);
        }
        return new Statement($this->connection, $statement_name, $visitor->get_parameter_map());
    }
    public function query(string $sql): Result
    {
        if (@pg_send_query($this->connection, $sql) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }
        $result = @pg_get_result($this->connection);
        assert($result !== false);
        if ((bool) pg_result_error($result)) {
            throw Exception::from_result($result);
        }
        return new Result($result);
    }
    /** {@inheritDoc} */
    public function quote(string $value): string
    {
        $quoted_value = pg_escape_literal($this->connection, $value);
        assert($quoted_value !== false);
        return $quoted_value;
    }
    public function exec(string $sql): int
    {
        return $this->query($sql)->row_count();
    }
    /** {@inheritDoc} */
    public function last_insert_id(): int|string
    {
        try {
            return $this->query('SELECT LASTVAL()')->fetch_one();
        } catch (Exception $exception) {
            if ($exception->get_sql_state() === '55000') {
                throw No_Identity_Value::new($exception);
            }
            throw $exception;
        }
    }
    public function begin_transaction(): void
    {
        $this->exec('BEGIN');
    }
    public function commit(): void
    {
        $this->exec('COMMIT');
    }
    public function roll_back(): void
    {
        $this->exec('ROLLBACK');
    }
    public function get_server_version(): string
    {
        return (string) pg_version($this->connection)['server'];
    }
    public function get_native_connection(): Pg_Sql_Connection
    {
        return $this->connection;
    }
}